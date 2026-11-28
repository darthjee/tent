<?php

namespace Tent\Middlewares;

use Tent\Content\CacheHit;
use Tent\Content\EvictionPolicy;
use Tent\Content\FileCache;
use Tent\Http\CurlHttpClient;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Service\BackgroundRefresher;
use Tent\Utils\FileUtils;

/**
 * Middleware that serves stale cache hits immediately while triggering a background refresh.
 *
 * `CacheStalenessMiddleware` pairs with a `FileCacheMiddleware` of the same handler: it acts in
 * the response phase ({@see processResponse()}), and only on responses which
 * `FileCacheMiddleware` served from cache, recognized by the {@see CacheHit} marker recorded on
 * the request. A response produced by anything else (e.g. proxied, or set by another
 * middleware) never triggers a refresh, even when a cache entry exists for the request.
 *
 * As every middleware of a handler runs in the response phase, it works wherever it is placed:
 * after `FileCacheMiddleware`, before it, or in `prependMiddlewares`. Configuring it **after**
 * `FileCacheMiddleware`, with the same `location`, is recommended for readability. The entry
 * which is checked and refreshed is always the one which was served.
 *
 * When the cached entry's age (`time() - FileCache::timestamp()`) exceeds `maxAgeSeconds`,
 * the stale response is still returned as-is, but a `BackgroundRefresher` is scheduled to
 * re-contact `host` and overwrite the cache entry for future requests. The refresh replays the
 * request as it was when the cache hit was served: request middlewares placed after
 * `FileCacheMiddleware` do not run on a cache hit, so their changes are not part of it.
 *
 * ## Debounce and abandoned refreshes
 *
 * While a refresh is scheduled or running, a sentinel file holding the time it was written sits
 * next to the cache entry's meta file, and further stale hits of that entry do not schedule
 * another one. The sentinel is removed when the refresh finishes.
 *
 * A refresh whose process dies (fatal error, killed worker, container restart, ...) leaves its
 * sentinel behind. Hence a sentinel older than `sentinelTtlSeconds` (or `sentinel_ttl_seconds`)
 * is considered abandoned: the next stale hit of the entry overwrites it, schedules the refresh
 * and logs a warning. The TTL defaults to 60 seconds, twice the 30 seconds total timeout of the
 * refresh HTTP client, and does not depend on `maxAgeSeconds`. A value `<= 0` disables the
 * expiry: the sentinel then debounces until it is removed.
 *
 * The sentinel is a best-effort debounce, not a lock: concurrent stale hits, or a refresh
 * outliving the TTL, may result in more than one refresh of the same entry.
 *
 * ## Non-blocking caveat
 *
 * Tent has no real async/job-queue infrastructure. "Background" execution is approximated via
 * `fastcgi_finish_request()` + `register_shutdown_function()` when available (PHP-FPM): the
 * response is flushed to the client first, then the refresh runs in the same PHP process during
 * shutdown. When `fastcgi_finish_request()` is not available (mod_php, CLI, built-in server),
 * the refresh runs synchronously, before the response is returned.
 *
 * **This is the case of the shipped `php:8.4-apache` (mod_php) image**: there, a request
 * hitting a stale entry waits for a full upstream round trip (unless a refresh of that entry is
 * already in flight). Do not rely on "background" being non-blocking outside of PHP-FPM.
 *
 * ## Example configuration
 *
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
 *             'location' => './cache'
 *         ],
 *         [
 *             'class' => 'Tent\\Middlewares\\CacheStalenessMiddleware',
 *             'location' => './cache',
 *             'host' => 'http://api:80',
 *             'maxAgeSeconds' => 300
 *         ]
 *     ]
 * ]);
 * ```
 *
 * ## Header filtering
 *
 * The refresh write uses the header filter of the `FileCacheMiddleware` which served the entry,
 * so that it strips exactly the same headers as a direct cache write.
 *
 * The `mode`, `excluded_headers`, `additional_excluded_headers` and `allowed_headers` options
 * of this middleware are **deprecated**: they are still accepted, but ignored (and not
 * validated), and a warning is logged when any of them is configured. They will be removed in
 * the next major version.
 *
 * ## Eviction on failed refresh
 *
 * By default a non-successful (non-2xx) refresh response keeps the stale entry. Configure
 * `evictOn` (or `evict_on`) with a list of status codes or wildcard patterns (e.g. `['4xx', 500]`)
 * to remove the cache entry when the upstream answers with a matching code. Defaults to `[]`
 * (never evict).
 *
 * Transport failures (non-zero cURL `errno`, e.g. a connection failure or an interrupted
 * transfer, whatever status code came with it) and exceptions never replace nor evict the
 * entry: it is kept untouched and a warning is logged.
 */
class CacheStalenessMiddleware extends Middleware
{
    /**
     * Suffix used for the debounce sentinel file written while a refresh is in flight.
     *
     * Appended to a cache file path, so it must not be longer than
     * {@see FileCache::DERIVED_SUFFIX_HEADROOM}.
     */
    public const REFRESH_SENTINEL_SUFFIX = '.refreshing';

    /**
     * Default sentinel TTL, in seconds: twice the fixed 30s total cURL timeout of the
     * refresh HTTP client, so that a healthy refresh is not taken over.
     */
    private const DEFAULT_SENTINEL_TTL_SECONDS = 60;

    /**
     * Maximum number of bytes read from a sentinel file.
     */
    private const SENTINEL_READ_LIMIT = 32;

    /**
     * Header filtering attributes, accepted for backward compatibility but ignored.
     */
    private const DEPRECATED_ATTRIBUTES = [
        'mode',
        'excluded_headers',
        'additional_excluded_headers',
        'allowed_headers',
    ];

    /**
     * @var integer Maximum age, in seconds, before a cache entry is considered stale.
     */
    private int $maxAgeSeconds;

    /**
     * @var string Base URL of the upstream server to re-contact on refresh.
     */
    private string $host;

    /**
     * @var HttpClientInterface The HTTP client used to perform the upstream refresh request.
     */
    private HttpClientInterface $httpClient;

    /**
     * @var callable Receives a `BackgroundRefresher` and is responsible for running it
     *               (immediately, or deferred via `register_shutdown_function()`).
     */
    private $scheduler;

    /**
     * @var EvictionPolicy Status codes that evict the cache entry when a refresh gets them.
     */
    private EvictionPolicy $evictionPolicy;

    /**
     * @var integer Age, in seconds, after which a sentinel is abandoned (`<= 0`: never).
     */
    private int $sentinelTtlSeconds;

    /**
     * @param FolderLocation           $location                  Base folder location for caching,
     *                                                            the same as the one of
     *                                                            `FileCacheMiddleware`. The entry
     *                                                            checked and refreshed is the one
     *                                                            which was served.
     * @param integer                  $maxAgeSeconds             Maximum age, in seconds, before
     *                                                            staleness.
     * @param string                   $host                      Base URL of the upstream server.
     * @param HttpClientInterface|null $httpClient                Optional HTTP client. Defaults to
     *                                                             CurlHttpClient.
     * @param callable|null            $scheduler                 Optional override for how refreshes
     *                                                             are run. Receives a
     *                                                             `BackgroundRefresher` instance.
     * @param array|null               $excludedHeaders           Ignored (@deprecated, to be removed
     *                                                            in the next major version).
     * @param array|null               $additionalExcludedHeaders Ignored (@deprecated, to be removed
     *                                                            in the next major version).
     * @param array|null               $allowedHeaders            Ignored (@deprecated, to be removed
     *                                                            in the next major version).
     * @param string                   $mode                      Ignored (@deprecated, to be removed
     *                                                            in the next major version).
     * @param array|null               $evictOn                   Status codes/patterns (e.g. '4xx')
     *                                                            that evict the entry when a refresh
     *                                                            receives them. Defaults to none.
     * @param integer|null             $sentinelTtlSeconds        Age, in seconds, after which the
     *                                                            sentinel of a refresh is abandoned
     *                                                            and taken over. Defaults to 60,
     *                                                            twice the 30s total cURL timeout
     *                                                            of the refresh client. A value
     *                                                            `<= 0` means it never expires.
     * @throws \InvalidArgumentException If $evictOn has invalid entries.
     */
    public function __construct(
        FolderLocation $location,
        int $maxAgeSeconds,
        string $host,
        ?HttpClientInterface $httpClient = null,
        ?callable $scheduler = null,
        ?array $excludedHeaders = null,
        ?array $additionalExcludedHeaders = null,
        ?array $allowedHeaders = null,
        string $mode = 'deny',
        ?array $evictOn = null,
        ?int $sentinelTtlSeconds = null
    ) {
        $this->maxAgeSeconds = $maxAgeSeconds;
        $this->host = $host;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->scheduler = $scheduler ?? self::defaultScheduler(...);
        $this->evictionPolicy = EvictionPolicy::build($evictOn);
        $this->sentinelTtlSeconds = $sentinelTtlSeconds ?? self::DEFAULT_SENTINEL_TTL_SECONDS;
    }

    /**
     * Builds a CacheStalenessMiddleware instance from the given attributes.
     *
     * A warning is logged when any of the deprecated header filtering attributes (`mode`,
     * `excluded_headers`, `additional_excluded_headers`, `allowed_headers`) is present.
     * They are otherwise ignored.
     *
     * `sentinel_ttl_seconds` (or `sentinelTtlSeconds`) is the age, in seconds, after which the
     * sentinel of a refresh is abandoned and taken over. It defaults to 60, whatever
     * `maxAgeSeconds` is. Pass a plain integer: the value goes through PHP's integer cast, so a
     * string with no leading number (e.g. `'abc'`) becomes `0`, one with a leading number keeps
     * it (`'5m'` means 5 seconds) and fractions are truncated (`0.5` becomes `0`). An explicit
     * value which is, or is cast to, `<= 0` means the sentinel never expires.
     *
     * @param array $attributes Must include `location`, `host` and `maxAgeSeconds`
     *                          (or `max_age_seconds`). Optionally `evictOn` (or `evict_on`)
     *                          and `sentinel_ttl_seconds` (or `sentinelTtlSeconds`).
     * @throws \InvalidArgumentException If `evictOn` is not an array or has invalid entries.
     * @return CacheStalenessMiddleware
     */
    public static function build(array $attributes): CacheStalenessMiddleware
    {
        self::warnDeprecatedAttributes($attributes);

        $location = new FolderLocation($attributes['location']);
        $maxAgeSeconds = (int) ($attributes['maxAgeSeconds'] ?? $attributes['max_age_seconds'] ?? 0);
        $host = $attributes['host'] ?? '';
        $evictOn = $attributes['evictOn'] ?? $attributes['evict_on'] ?? null;
        $sentinelTtl = $attributes['sentinel_ttl_seconds'] ?? $attributes['sentinelTtlSeconds'] ?? null;
        // An absent value must stay null: cast to 0, it would mean "never expires".
        $sentinelTtlSeconds = $sentinelTtl === null ? null : (int) $sentinelTtl;

        if ($evictOn !== null && !is_array($evictOn)) {
            throw new \InvalidArgumentException('evictOn must be an array');
        }

        return new self(
            $location,
            $maxAgeSeconds,
            $host,
            evictOn: $evictOn,
            sentinelTtlSeconds: $sentinelTtlSeconds
        );
    }

    /**
     * Detects staleness of a response served from cache and schedules a background refresh
     * when needed.
     *
     * No-op when the response's request is not a `ProcessingRequest`, was flagged with
     * `skipCache()` (checked before anything else is read, so no refresh is scheduled and
     * nothing is evicted), or carries no {@see CacheHit} marker (the response was not served
     * from cache). Also a no-op when the served entry has no timestamp or is still fresh.
     *
     * The response is never modified by this middleware.
     *
     * @param Response $response The response being sent back.
     * @return Response The unmodified response.
     */
    public function processResponse(Response $response): Response
    {
        $request = $response->request();

        if (!$request instanceof ProcessingRequest || $request->cacheSkipped()) {
            return $response;
        }

        $hit = $request->cacheHit();

        if ($hit !== null) {
            $this->refreshWhenStale($request, $hit);
        }

        return $response;
    }

    /**
     * Triggers a refresh when the served cache entry is older than `maxAgeSeconds`.
     *
     * @param ProcessingRequest $request The request which was served from cache.
     * @param CacheHit          $hit     The served cache entry and its header filter.
     * @return void
     */
    private function refreshWhenStale(ProcessingRequest $request, CacheHit $hit): void
    {
        $timestamp = $hit->cache()->timestamp();

        if ($timestamp === null) {
            return;
        }

        $age = time() - $timestamp;

        if ($age <= $this->maxAgeSeconds) {
            return;
        }

        Logger::debug(
            '[stale] - cache age exceeds threshold — uri: ' . $request->requestPath() .
            ', age: ' . $age . ', maxAgeSeconds: ' . $this->maxAgeSeconds
        );

        $this->triggerRefresh($request, $hit);
    }

    /**
     * Triggers a background refresh for the given request/cache hit pair, unless one is
     * already in flight (debounced via a sentinel file). An abandoned sentinel, older than the
     * sentinel TTL, does not debounce: it is overwritten and the refresh is scheduled.
     *
     * @param ProcessingRequest $request The request which was served from cache.
     * @param CacheHit          $hit     The stale cache hit to refresh.
     * @return void
     */
    private function triggerRefresh(ProcessingRequest $request, CacheHit $hit): void
    {
        $sentinel = $this->sentinelPath($hit->cache());

        if ($this->isRefreshInFlight($request, $sentinel)) {
            return;
        }

        $this->writeSentinel($sentinel);

        $this->scheduleRefresh($request, $hit, $sentinel);
    }

    /**
     * Tells whether the sentinel stands for a refresh in flight, logging the outcome when the
     * sentinel exists: a debug line when it debounces, a warning when it is abandoned.
     *
     * @param ProcessingRequest $request  The request which was served from cache.
     * @param string            $sentinel The debounce sentinel file path.
     * @return boolean True when no refresh must be scheduled.
     */
    private function isRefreshInFlight(ProcessingRequest $request, string $sentinel): bool
    {
        if (!file_exists($sentinel)) {
            return false;
        }

        // A TTL <= 0 never expires, so the sentinel is not even read.
        $age = $this->sentinelTtlSeconds <= 0 ? null : $this->sentinelAge($sentinel, time());

        if ($this->isFresh($age)) {
            Logger::debug('[stale] - refresh already in progress, skipping — uri: ' . $request->requestPath());
            return true;
        }

        Logger::warn(
            '[stale] - abandoned refresh sentinel taken over — uri: ' . $request->requestPath() .
            ', age: ' . $age . ', sentinelTtlSeconds: ' . $this->sentinelTtlSeconds
        );

        return false;
    }

    /**
     * The debounce rule: a sentinel is fresh while its age does not exceed the TTL. A disabled
     * TTL and a sentinel which cannot be dated both count as fresh.
     *
     * @param integer|null $age Age of the sentinel, in seconds, or null when unknown.
     * @return boolean
     */
    private function isFresh(?int $age): bool
    {
        return $this->sentinelTtlSeconds <= 0 || $age === null || $age <= $this->sentinelTtlSeconds;
    }

    /**
     * Builds a `BackgroundRefresher` for the given request/cache hit pair and hands it to the
     * scheduler, removing the debounce sentinel once the refresh finishes.
     *
     * @param ProcessingRequest $request  The request which was served from cache.
     * @param CacheHit          $hit      The stale cache hit to refresh.
     * @param string            $sentinel The debounce sentinel file path.
     * @return void
     */
    private function scheduleRefresh(ProcessingRequest $request, CacheHit $hit, string $sentinel): void
    {
        $refresher = new BackgroundRefresher(
            $request,
            $hit->cache(),
            $this->host,
            $this->httpClient,
            $hit->headerFilter(),
            $this->evictionPolicy
        );

        ($this->scheduler)(function () use ($refresher, $sentinel) {
            try {
                $refresher->run();
            } finally {
                self::removeSentinel($sentinel);
            }
        });
    }

    /**
     * Logs a warning when deprecated header filtering attributes are configured.
     *
     * @param array $attributes The attributes the middleware is being built from.
     * @return void
     */
    private static function warnDeprecatedAttributes(array $attributes): void
    {
        $present = array_intersect(self::DEPRECATED_ATTRIBUTES, array_keys($attributes));

        if ($present === []) {
            return;
        }

        Logger::warn(
            '[deprecated] - CacheStalenessMiddleware ignores option(s): ' . implode(', ', $present) .
            ' — the cache refresh uses the header filter of FileCacheMiddleware; ' .
            'they will be removed in the next major version'
        );
    }

    /**
     * Default scheduling strategy: defers execution to the `fastcgi_finish_request()` +
     * `register_shutdown_function()` combo when available (PHP-FPM), running the task
     * synchronously otherwise.
     *
     * @param callable $task The refresh task to run.
     * @return void
     */
    private static function defaultScheduler(callable $task): void
    {
        if (function_exists('fastcgi_finish_request')) {
            register_shutdown_function(function () use ($task) {
                fastcgi_finish_request();
                $task();
            });
            return;
        }

        $task();
    }

    /**
     * Builds the sentinel file path used to debounce concurrent refreshes for a cache entry.
     *
     * @param FileCache $cache The cache entry being refreshed.
     * @return string
     */
    private function sentinelPath(FileCache $cache): string
    {
        return $cache->metaFilePath() . self::REFRESH_SENTINEL_SUFFIX;
    }

    /**
     * Computes the age of a sentinel from the timestamp it holds, falling back to its
     * modification time when it holds no valid one.
     *
     * A timestamp more than one TTL in the future (an overflowing one included) is invalid, as
     * it would otherwise debounce for as long as it stays ahead.
     *
     * @param string  $sentinel The sentinel file path.
     * @param integer $now      The current Unix time.
     * @return integer|null The age in seconds (negative when dated in the future), or null when
     *                      the sentinel cannot be dated.
     */
    private function sentinelAge(string $sentinel, int $now): ?int
    {
        $stored = $this->storedTimestamp($sentinel);

        if ($stored !== null && $stored - $now <= $this->sentinelTtlSeconds) {
            return $now - $stored;
        }

        $modified = @filemtime($sentinel);

        return $modified === false ? null : $now - $modified;
    }

    /**
     * Reads the Unix timestamp held by a sentinel file.
     *
     * Only regular files (following links) are opened, as reading a FIFO or a device could
     * block or never end, and at most {@see SENTINEL_READ_LIMIT} bytes are read.
     *
     * @param string $sentinel The sentinel file path.
     * @return integer|null The timestamp, or null when it cannot be read or is not made of
     *                      digits only.
     */
    private function storedTimestamp(string $sentinel): ?int
    {
        if (!is_file($sentinel)) {
            return null;
        }

        $content = @file_get_contents($sentinel, false, null, 0, self::SENTINEL_READ_LIMIT);

        if ($content === false) {
            return null;
        }

        $content = trim($content);

        return ctype_digit($content) ? (int) $content : null;
    }

    /**
     * Writes the debounce sentinel file, ignoring failures (best-effort debounce).
     *
     * The file is written atomically with mode `0644`, and a symbolic link standing
     * in its place is replaced rather than followed (see
     * {@see FileUtils::writeAtomically()}). Its directory is the one of an existing
     * cache entry, so it is neither created nor checked here.
     *
     * @param string $sentinel The sentinel file path.
     * @return void
     */
    private function writeSentinel(string $sentinel): void
    {
        FileUtils::writeAtomically($sentinel, (string) time());
    }

    /**
     * Removes the debounce sentinel file, ignoring failures.
     *
     * @param string $sentinel The sentinel file path.
     * @return void
     */
    private static function removeSentinel(string $sentinel): void
    {
        if (file_exists($sentinel)) {
            @unlink($sentinel);
        }
    }
}
