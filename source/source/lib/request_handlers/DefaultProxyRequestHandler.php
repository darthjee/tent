<?php

namespace Tent\RequestHandlers;

use Tent\Http\HttpClientInterface;
use Tent\Middlewares\RenameHeaderMiddleware;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Middlewares\FilterQueryParamsMiddleware;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Cache\RequestHasher;

/**
 * A ProxyRequestHandler with a default middleware stack for common proxy behavior.
 *
 * Automatically configures:
 * 1. RenameHeaderMiddleware: renames `Host` to `X-Forwarded-Host`.
 * 2. SetHeadersMiddleware: sets `Host` to the provided host value.
 * 3. FilterQueryParamsMiddleware (optional): filters the request query string.
 * 4. FileCacheMiddleware (optional): caches responses matching the given HTTP codes.
 *
 * ## Usage Example
 *
 * @example Basic proxy configuration:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ]
 * ]);
 * ```
 *
 * @example Configuration without cache
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80',
 *         'cache' => false
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ]
 * ]);
 * ```
 *
 * @example Configuration with custom cache and cache codes
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80',
 *         'cache' => './custom_cache',
 *         'cacheCodes' => ['2xx', '302']
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ]
 * ]);
 * ```
 *
 * @example Configuration with query params filtering (runs before caching, so the
 * cache key reflects the filtered query)
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80',
 *         'filter_query_params' => [
 *             'params' => ['id', 'page'],
 *             'mode' => 'allow'
 *         ]
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ]
 * ]);
 * ```
 *
 * @example Configuration excluding additional headers from cache storage (in addition to
 * the built-in defaults — `Set-Cookie`, `Set-Cookie2`, `WWW-Authenticate`, `Proxy-Authenticate`)
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80',
 *         'additional_excluded_headers' => ['X-Internal-Token']
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ]
 * ]);
 * ```
 */
class DefaultProxyRequestHandler extends ProxyRequestHandler
{
    /**
     * @var string|false Cache directory or false to disable caching
     */
    private string|false $cache;
    /**
     * @var array HTTP status codes eligible for caching
     */
    private array $cacheCodes;
    /**
     * @var string|null Header name that forces cache bypass when present.
     */
    private ?string $skipCacheHeader;

    /**
     * @var RequestHasher|null Hasher used to derive the cache-key hash. Null defers the
     *                         default resolution to `FileCacheMiddleware`.
     */
    private ?RequestHasher $requestHasher;

    /**
     * @var array|null Configuration passed through to `FilterQueryParamsMiddleware::build()`.
     *                 Null disables query params filtering.
     */
    private ?array $filterQueryParams;

    /**
     * @var string|null Header name that must be present in the response for it to be cached.
     */
    private ?string $requireCacheHeader;

    /**
     * @var array|null Deny mode: full override of the excluded headers list. Null defaults to
     *                 `\Tent\Content\ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS`.
     */
    private ?array $excludedHeaders;

    /**
     * @var array|null Deny mode: merged on top of the resolved excluded headers list.
     */
    private ?array $additionalExcludedHeaders;

    /**
     * @var array|null Allow mode: explicit, complete list of the only headers kept in cache storage.
     */
    private ?array $allowedHeaders;

    /**
     * @var string Either 'deny' (default) or 'allow'.
     */
    private string $mode;

    /**
     * Constructs a DefaultProxyRequestHandler.
     *
     * @param string                   $host                      The target host to proxy requests to.
     * @param string|false             $cache                     Cache directory, or false to disable caching.
     *          Defaults to './cache'.
     * @param array                    $cacheCodes                HTTP status codes eligible for caching.
     *          Defaults to ['2xx'].
     * @param HttpClientInterface|null $httpClient                Optional HTTP client.
     * @param string|null              $skipCacheHeader           Header name that disables cache read/write
     *          when present.
     * @param RequestHasher|null       $requestHasher             Hasher used to derive the cache-key hash. Defaults to
     *          {@see \Tent\Cache\QueryRequestHasher}, resolved by `FileCacheMiddleware`.
     * @param array|null               $filterQueryParams         Configuration passed through to
     *          `FilterQueryParamsMiddleware::build()`. Null disables query params filtering.
     * @param string|null              $requireCacheHeader        Header name that must be present in the response
     *          for it to be cached.
     * @param array|null               $excludedHeaders           Deny mode: full override of the excluded headers
     *          list.
     * @param array|null               $additionalExcludedHeaders Deny mode: merged on top of the resolved
     *   excluded headers list.
     * @param array|null               $allowedHeaders            Allow mode: explicit, complete list of the only
     *          headers kept in cache storage.
     * @param string                   $mode                      Either 'deny' (default) or 'allow'.
     */
    public function __construct(
        string $host,
        string|false $cache,
        array $cacheCodes,
        ?HttpClientInterface $httpClient = null,
        ?string $skipCacheHeader = null,
        ?RequestHasher $requestHasher = null,
        ?array $filterQueryParams = null,
        ?string $requireCacheHeader = null,
        ?array $excludedHeaders = null,
        ?array $additionalExcludedHeaders = null,
        ?array $allowedHeaders = null,
        string $mode = 'deny'
    ) {
        parent::__construct($host, $httpClient);
        $this->cache = $cache;
        $this->cacheCodes = $cacheCodes;
        $this->skipCacheHeader = $skipCacheHeader;
        $this->requestHasher = $requestHasher;
        $this->filterQueryParams = $filterQueryParams;
        $this->requireCacheHeader = $requireCacheHeader;
        $this->excludedHeaders = $excludedHeaders;
        $this->additionalExcludedHeaders = $additionalExcludedHeaders;
        $this->allowedHeaders = $allowedHeaders;
        $this->mode = $mode;
        $this->initializeMiddlewares();
    }

    /**
     * Builds a DefaultProxyRequestHandler from an associative array of parameters.
     *
     * @param array $params Associative array with keys:
     *   - 'host' (string, required): Target host URL.
     *   - 'cache' (string|false): Cache directory or false to disable. Defaults to './cache'.
     *   - 'cacheCodes' (array): HTTP codes to cache. Defaults to ['2xx'].
     *   - 'skip_cache_header' (string): Header name that disables cache read/write when present.
     *   - 'request_hasher' (array): RequestHasher configuration (`class` key), following the same
     *     strategy pattern as matchers. Defaults to `QueryRequestHasher` when omitted.
     *   - 'filter_query_params' (array): Configuration passed through to
     *     `FilterQueryParamsMiddleware::build()`. Defaults to null (disabled).
     *   - 'require_cache_header' (string): Header name that must be present in the response for
     *     it to be cached.
     *   - 'mode' (string): Either 'deny' (default) or 'allow', controlling how the header-filtering
     *     options below are interpreted.
     *   - 'excluded_headers' (array): Deny mode: full override of the excluded headers list.
     *     Defaults to `\Tent\Content\ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS` when omitted.
     *   - 'additional_excluded_headers' (array): Deny mode: always merged on top of the resolved
     *     excluded headers list.
     *   - 'allowed_headers' (array): Allow mode: explicit, complete list of the only headers kept
     *     in cache storage. Required and non-empty when 'mode' is 'allow'.
     * @return self
     * @throws \InvalidArgumentException If 'host' is missing, 'mode' is invalid, or 'allow' mode
     *   has an empty/missing 'allowed_headers'.
     */
    public static function build(array $params): self
    {
        if (!isset($params['host'])) {
            throw new \InvalidArgumentException("Missing required parameter 'host'");
        }
        $host = $params['host'];
        $cache = array_key_exists('cache', $params) ? $params['cache'] : './cache';
        $cacheCodes = $params['cacheCodes'] ?? ['2xx'];
        $skipCacheHeader = $params['skip_cache_header'] ?? null;
        $requestHasher = self::buildRequestHasher($params);
        $filterQueryParams = $params['filter_query_params'] ?? null;
        $requireCacheHeader = $params['require_cache_header'] ?? null;
        $excludedHeaders = $params['excluded_headers'] ?? null;
        $additionalExcludedHeaders = $params['additional_excluded_headers'] ?? null;
        $allowedHeaders = $params['allowed_headers'] ?? null;
        $mode = $params['mode'] ?? 'deny';
        return new self(
            $host,
            $cache,
            $cacheCodes,
            null,
            $skipCacheHeader,
            $requestHasher,
            $filterQueryParams,
            $requireCacheHeader,
            $excludedHeaders,
            $additionalExcludedHeaders,
            $allowedHeaders,
            $mode
        );
    }

    /**
     * Initializes the middleware stack in the correct order.
     * @return void
     */
    private function initializeMiddlewares(): void
    {
        $this->addMiddleware(new RenameHeaderMiddleware('Host', 'X-Forwarded-Host'));
        $this->addMiddleware(new SetHeadersMiddleware(['Host' => $this->host()]));

        if ($this->filterQueryParams !== null) {
            $this->addMiddleware(FilterQueryParamsMiddleware::build($this->filterQueryParams));
        }

        if ($this->cache !== false) {
            $this->addMiddleware(new FileCacheMiddleware(
                new FolderLocation($this->cache),
                [new StatusCodeMatcher($this->cacheCodes)],
                $this->skipCacheHeader,
                $this->requestHasher,
                $this->requireCacheHeader,
                $this->excludedHeaders,
                $this->additionalExcludedHeaders,
                $this->allowedHeaders,
                $this->mode
            ));
        }
    }

    /**
     * Builds the configured RequestHasher, if any.
     *
     * @param array $params The build parameters.
     * @return RequestHasher|null The constructed hasher, or null when not configured.
     */
    private static function buildRequestHasher(array $params): ?RequestHasher
    {
        if (!isset($params['request_hasher'])) {
            return null;
        }

        $config = $params['request_hasher'];
        $class = $config['class'];

        return $class::build($config);
    }
}
