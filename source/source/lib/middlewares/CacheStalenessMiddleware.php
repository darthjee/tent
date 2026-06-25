<?php

namespace Tent\Middlewares;

use Tent\Content\FileCache;
use Tent\Http\CurlHttpClient;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Service\BackgroundRefresher;

/**
 * Middleware that serves stale cache hits immediately while triggering a background refresh.
 *
 * `CacheStalenessMiddleware` must be configured **after** `FileCacheMiddleware` in a rule's
 * `middlewares` list, sharing the same `location`: it only evaluates staleness when a cache
 * hit has already set a response on the request (i.e. `FileCacheMiddleware::processRequest()`
 * ran first and found an entry).
 *
 * When the cached entry's age (`time() - FileCache::timestamp()`) exceeds `maxAgeSeconds`,
 * the stale response is still returned as-is, but a `BackgroundRefresher` is scheduled to
 * re-contact `host` and overwrite the cache entry for future requests.
 *
 * ## Non-blocking caveat
 *
 * Tent runs under PHP-FPM/Apache with no real async/job-queue infrastructure. "Background"
 * execution is approximated via `fastcgi_finish_request()` + `register_shutdown_function()`
 * when available (PHP-FPM): the response is flushed to the client first, then the refresh
 * runs in the same PHP process during shutdown. When `fastcgi_finish_request()` is not
 * available (e.g. CLI, built-in server, some Apache SAPIs), the refresh runs synchronously
 * before the response middleware chain finishes — i.e. it *will* add upstream latency to
 * the current request in that case. Do not rely on "background" being non-blocking outside
 * of PHP-FPM.
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
 */
class CacheStalenessMiddleware extends Middleware
{
    /**
     * Suffix used for the debounce sentinel file written while a refresh is in flight.
     */
    private const REFRESH_SENTINEL_SUFFIX = '.refreshing';

    /**
     * @var FolderLocation The base folder location for caching (must match FileCacheMiddleware).
     */
    private FolderLocation $location;

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
     * @param FolderLocation           $location      Base folder location for caching.
     * @param integer                  $maxAgeSeconds Maximum age, in seconds, before staleness.
     * @param string                   $host          Base URL of the upstream server.
     * @param HttpClientInterface|null $httpClient    Optional HTTP client. Defaults to CurlHttpClient.
     * @param callable|null            $scheduler     Optional override for how refreshes are run.
     *                                                Receives a `BackgroundRefresher` instance.
     */
    public function __construct(
        FolderLocation $location,
        int $maxAgeSeconds,
        string $host,
        ?HttpClientInterface $httpClient = null,
        ?callable $scheduler = null
    ) {
        $this->location = $location;
        $this->maxAgeSeconds = $maxAgeSeconds;
        $this->host = $host;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->scheduler = $scheduler ?? self::defaultScheduler(...);
    }

    /**
     * Builds a CacheStalenessMiddleware instance from the given attributes.
     *
     * @param array $attributes Must include `location`, `host` and `maxAgeSeconds`
     *                          (or `max_age_seconds`).
     * @return CacheStalenessMiddleware
     */
    public static function build(array $attributes): CacheStalenessMiddleware
    {
        $location = new FolderLocation($attributes['location']);
        $maxAgeSeconds = (int) ($attributes['maxAgeSeconds'] ?? $attributes['max_age_seconds'] ?? 0);
        $host = $attributes['host'] ?? '';

        return new self($location, $maxAgeSeconds, $host);
    }

    /**
     * Detects staleness on a cache hit and schedules a background refresh when needed.
     *
     * No-op when the request has no response set yet (i.e. no cache hit occurred),
     * or when the cached entry is still fresh. The (already cached) response is
     * never modified by this middleware.
     *
     * @param ProcessingRequest $request The incoming processing request.
     * @return ProcessingRequest The unmodified processing request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        if (!$request->hasResponse()) {
            return $request;
        }

        $cache = new FileCache($request, $this->location);
        $timestamp = $cache->timestamp();

        if ($timestamp === null) {
            return $request;
        }

        $age = time() - $timestamp;

        if ($age <= $this->maxAgeSeconds) {
            return $request;
        }

        Logger::debug(
            '[stale] - cache age exceeds threshold — uri: ' . $request->requestPath() .
            ', age: ' . $age . ', maxAgeSeconds: ' . $this->maxAgeSeconds
        );

        $this->triggerRefresh($request, $cache);

        return $request;
    }

    /**
     * Triggers a background refresh for the given request/cache pair, unless one is
     * already in flight (debounced via a sentinel file).
     *
     * @param ProcessingRequest $request The incoming processing request.
     * @param FileCache         $cache   The stale cache entry to refresh.
     * @return void
     */
    private function triggerRefresh(ProcessingRequest $request, FileCache $cache): void
    {
        $sentinel = $this->sentinelPath($cache);

        if (file_exists($sentinel)) {
            Logger::debug('[stale] - refresh already in progress, skipping — uri: ' . $request->requestPath());
            return;
        }

        $this->writeSentinel($sentinel);

        $this->scheduleRefresh($request, $cache, $sentinel);
    }

    /**
     * Builds a `BackgroundRefresher` for the given request/cache pair and hands it to the
     * scheduler, removing the debounce sentinel once the refresh finishes.
     *
     * @param ProcessingRequest $request  The incoming processing request.
     * @param FileCache         $cache    The stale cache entry to refresh.
     * @param string            $sentinel The debounce sentinel file path.
     * @return void
     */
    private function scheduleRefresh(ProcessingRequest $request, FileCache $cache, string $sentinel): void
    {
        $refresher = new BackgroundRefresher($request, $cache, $this->host, $this->httpClient);

        ($this->scheduler)(function () use ($refresher, $sentinel) {
            try {
                $refresher->run();
            } finally {
                self::removeSentinel($sentinel);
            }
        });
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
     * Writes the debounce sentinel file, ignoring failures (best-effort debounce).
     *
     * @param string $sentinel The sentinel file path.
     * @return void
     */
    private function writeSentinel(string $sentinel): void
    {
        @file_put_contents($sentinel, (string) time());
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
