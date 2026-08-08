<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Service\ResponseCacher;
use Tent\Matchers\RequestResponseMatcher;
use Tent\Models\RequestInterface;
use Tent\Cache\RequestHasher;
use Tent\Cache\QueryRequestHasher;

/**
 * Middleware for caching responses to files.
 *
 * ## Usage Example
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
 *             'location' => './cache',
 *             'matchers' => [
 *                 [
 *                     'class' => 'Tent\\Matchers\\StatusCodeMatcher',
 *                     'httpCodes' => [200] // or ["2xx"] for all 2xx codes
 *                 ],
 *                 [
 *                     'class' => 'Tent\\Matchers\\RequestMethodMatcher',
 *                     'requestMethods' => ['GET', 'POST']
 *                 ]
 *             ],
 *             'request_hasher' => [
 *                 'class' => 'Tent\\Cache\\QueryRequestHasher'
 *             ]
 *         ]
 *     ]
 * ]);
 * ```
 *
 * - `location`: Directory where cached responses are stored (required).
 * - `matchers`: Array of matcher configurations to determine cacheability.
 * - `request_hasher`: Optional `RequestHasher` configuration (`class` key, following the same
 *   strategy pattern as matchers) used to derive the cache-key hash. Defaults to
 *   {@see \Tent\Cache\QueryRequestHasher} when omitted.
 * - `skip_cache_header`: Optional header name that disables cache read/write when present in the
 *   request or response.
 * - `require_cache_header`: Optional header name that must be present in the response for it to be
 *   cached. Only gates the cache write path; request-side presence is never checked.
 *
 * This middleware will cache responses matching all configured matchers,
 * and serve them from cache on subsequent requests.
 */
class FileCacheMiddleware extends Middleware
{
    /**
     * @var FolderLocation The base folder location for caching.
     */
    private FolderLocation $location;

    /**
     * @var array The list of response matchers to determine cacheability.
     */
    private array $matchers;
    /**
     * @var string|null Header name that forces cache bypass when present in request.
     */
    private ?string $skipCacheHeader;

    /**
     * @var RequestHasher Hasher used to derive the cache-key hash for each request.
     */
    private RequestHasher $requestHasher;

    /**
     * @var string|null Header name that must be present in the response for it to be cached.
     */
    private ?string $requireCacheHeader;

    /**
     * Constructs a FileCacheMiddleware instance.
     *
     * @param FolderLocation     $location           The base folder location for caching.
     * @param array              $matchers           Array of custom matchers for cacheability.
     * @param string|null        $skipCacheHeader    Header name that disables cache read/write when present.
     * @param RequestHasher|null $requestHasher      Hasher used to derive the cache-key hash.
     *                                                Defaults to {@see QueryRequestHasher}.
     * @param string|null        $requireCacheHeader Header name that must be present in the response for
     *                                                it to be cached.
     */
    public function __construct(
        FolderLocation $location,
        array $matchers = [],
        ?string $skipCacheHeader = null,
        ?RequestHasher $requestHasher = null,
        ?string $requireCacheHeader = null
    ) {
        $this->location = $location;

        $this->matchers = $matchers;
        $this->skipCacheHeader = $skipCacheHeader;
        $this->requestHasher = $requestHasher ?? new QueryRequestHasher();
        $this->requireCacheHeader = $requireCacheHeader;
    }

    /**
     * Builds a FileCacheMiddleware instance from the given attributes.
     *
     * @param array $attributes The attributes to build the middleware.
     * @return FileCacheMiddleware The constructed FileCacheMiddleware instance.
     */
    public static function build(array $attributes): FileCacheMiddleware
    {
        $location = new FolderLocation($attributes['location']);
        $matchers = RequestResponseMatcher::buildMatchers($attributes['matchers'] ?? []);
        $skipCacheHeader = $attributes['skip_cache_header'] ?? null;
        $requestHasher = self::buildRequestHasher($attributes);
        $requireCacheHeader = $attributes['require_cache_header'] ?? null;

        return new self($location, $matchers, $skipCacheHeader, $requestHasher, $requireCacheHeader);
    }

    /**
     * Processes the incoming request.
     *
    * Only attempts to read from cache if the request method is included in the configured
    * requestMethods filter and matches all configured matchers. If not allowed, the request
    * is returned unmodified.
     *
     * If a cached response exists for the request path, it is loaded and set on the request.
     *
     * @param ProcessingRequest $request The incoming processing request.
     * @return ProcessingRequest The (potentially cached) processing request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        if ($this->shouldSkipCache($request)) {
            return $request;
        }

        foreach ($this->matchers as $matcher) {
            if (!$matcher->matchRequest($request)) {
                return $request;
            }
        }

        $cache = new FileCache($request, $this->location, $this->requestHasher);

        if ($cache->exists()) {
            $reader = new ResponseContentReader($request, $cache);
            $response = $reader->getResponse();
            Logger::debug('[' . $response->httpCode() . '] - serving from cache — uri: ' . $request->requestPath());
            $request->setResponse($response);
        }

        return $request;
    }

    /**
     * Caches the response to a file.
     *
     * Only stores the response if it matches all configured matchers.
     *
     * @param Response $response The response to cache.
     * @return Response The original response.
     */
    public function processResponse(Response $response): Response
    {
        if ($this->shouldSkipCacheWrite($response)) {
            return $response;
        }

        if ($this->isCacheable($response)) {
            $cache = new FileCache($response->request(), $this->location, $this->requestHasher);
            (new ResponseCacher($cache, $response))->process();
        }
        return $response;
    }

    /**
     * Check if the response is cacheable based on the configured matchers.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response is storable, false otherwise.
     */
    private function isCacheable(Response $response): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->matchResponse($response)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks whether cache read should be bypassed based on the incoming request headers.
     *
     * @param RequestInterface $request Current request.
     * @return boolean True when skip header is configured and present in the request, false otherwise.
     */
    private function shouldSkipCache(RequestInterface $request): bool
    {
        if ($this->skipCacheHeader === null) {
            return false;
        }

        $normalizedHeaders = array_change_key_case($request->headers(), CASE_LOWER);
        return array_key_exists(strtolower($this->skipCacheHeader), $normalizedHeaders);
    }

    /**
     * Checks whether cache write should be bypassed based on the upstream response headers.
     *
     * @param Response $response Upstream response.
     * @return boolean True when skip header is configured and present in the response, false otherwise.
     */
    private function shouldSkipCacheForResponse(Response $response): bool
    {
        if ($this->skipCacheHeader === null) {
            return false;
        }

        $headerPrefix = strtolower($this->skipCacheHeader) . ':';
        foreach ($response->headers() as $headerLine) {
            if (str_starts_with(strtolower($headerLine), $headerPrefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether the response satisfies the configured require-cache header.
     *
     * @param Response $response Upstream response.
     * @return boolean True when no require header is configured, or when it is present in the
     *                  response, false otherwise.
     */
    private function meetsRequireCacheHeader(Response $response): bool
    {
        if ($this->requireCacheHeader === null) {
            return true;
        }

        $headerPrefix = strtolower($this->requireCacheHeader) . ':';
        foreach ($response->headers() as $headerLine) {
            if (str_starts_with(strtolower($headerLine), $headerPrefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether the cache write should be skipped for the given response.
     *
     * @param Response $response Upstream response.
     * @return boolean True when the response should not be cached, false otherwise.
     */
    private function shouldSkipCacheWrite(Response $response): bool
    {
        return $this->shouldSkipCacheForResponse($response)
            || $this->shouldSkipCache($response->request())
            || !$this->meetsRequireCacheHeader($response);
    }

    /**
     * Builds the configured RequestHasher, if any.
     *
     * @param array $attributes The attributes to build the middleware.
     * @return RequestHasher|null The constructed hasher, or null when not configured.
     */
    private static function buildRequestHasher(array $attributes): ?RequestHasher
    {
        if (!isset($attributes['request_hasher'])) {
            return null;
        }

        $config = $attributes['request_hasher'];
        $class = $config['class'];

        return $class::build($config);
    }
}
