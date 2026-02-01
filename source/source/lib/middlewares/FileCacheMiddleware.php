<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Models\FileCache;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Models\ResponseMatchers\StatusCodeMatcher;
use Tent\Service\ResponseCacher;

/**
 * Middleware for caching responses to files.
 */
class FileCacheMiddleware extends Middleware
{
    /**
     * @var FolderLocation The base folder location for caching.
     */
    private FolderLocation $location;

    /**
     * @var array The list of HTTP request methods to cache.
     */
    private array $requestMethods;

    /**
     * @var array The list of response matchers to determine cacheability.
     */
    private array $matchers;

    /**
     * Constructs a FileCacheMiddleware instance.
     *
     * @param FolderLocation $location       The base folder location for caching.
     * @param array|null     $httpCodes      Array of HTTP status codes to cache. Defaults to [200].
     * @param array|null     $requestMethods Array of HTTP request methods to cache. Defaults to ['GET'].
     */
    public function __construct(FolderLocation $location, ?array $httpCodes = null, ?array $requestMethods = null)
    {
        $this->location = $location;
        $this->requestMethods = $requestMethods ?? ['GET'];

        $httpCodes = $httpCodes ?? [200];
        $this->matchers = [new StatusCodeMatcher($httpCodes)];
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
        $httpCodes = $attributes['httpCodes'] ?? null;
        $requestMethods = $attributes['requestMethods'] ?? null;
        return new self($location, $httpCodes, $requestMethods);
    }

    /**
     * Processes the incoming request.
     *
     * Only attempts to read from cache if the request method is included in the configured
     * requestMethods filter. If the method is not allowed, the request is returned unmodified.
     *
     * If a cached response exists for the request path, it is loaded and set on the request.
     *
     * @param ProcessingRequest $request The incoming processing request.
     * @return ProcessingRequest The (potentially cached) processing request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        if (!in_array($request->requestMethod(), $this->requestMethods, true)) {
            return $request;
        }

        $cache = new FileCache($request, $this->location);

        if ($cache->exists()) {
            $reader = new ResponseContentReader($request, $cache);
            $response = $reader->getResponse();
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
        if ($this->isCacheable($response)) {
            $cache = new FileCache($response->request(), $this->location);
            new ResponseCacher($cache, $response)->process();
        }
        return $response;
    }

    private function isCacheable(Response $response): bool
    {
        $cache = new FileCache($response->request(), $this->location);
        return !$cache->exists() && $this->responseMatch($response);
    }

    /**
     * Check if the response is cacheable based on the configured matchers.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response is storable, false otherwise.
     */
    private function responseMatch(Response $response): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->match($response)) {
                return false;
            }
        }
        return true;
    }
}
