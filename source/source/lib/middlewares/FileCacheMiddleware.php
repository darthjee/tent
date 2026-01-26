<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Models\FileCache;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Utils\HttpCodeMatcher;

/**
 * Middleware for caching responses to files.
 */
class FileCacheMiddleware extends Middleware
{
    private FolderLocation $location;
    private array $httpCodes;
    private array $requestMethods;

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
        $this->httpCodes = $httpCodes ?? [200];
        $this->requestMethods = $requestMethods ?? ['GET'];
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
     * Only stores the response if its HTTP status code is included in the configured httpCodes filter.
     * If the code is not allowed, the response is returned without caching.
     *
     * @param Response $response The response to cache.
     * @return Response The original response.
     */
    public function processResponse(Response $response): Response
    {
        if ($response && HttpCodeMatcher::matchAny($response->httpCode(), $this->httpCodes)) {
            $cache = new FileCache($response->request(), $this->location);
            $cache->store($response);
        }
        return $response;
    }
}
