<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Models\FileCache;
use Tent\Models\Response;

/**
 * Middleware for caching responses to files.
 */
class FileCacheMiddleware extends Middleware
{
    private FolderLocation $location;

    /**
     * Constructs a FileCacheMiddleware instance.
     *
     * @param FolderLocation $location The base folder location for caching.
     */
    public function __construct(FolderLocation $location)
    {
        $this->location = $location;
    }

    /**
     * Builds a FileCacheMiddleware instance from the given attributes.
     *
     * @param array $attributes The attributes to build the middleware.
     * @return FileCacheMiddleware The constructed FileCacheMiddleware instance.
     */
    public static function build(array $attributes): FileCacheMiddleware
    {
        $location = new FolderLocation($attributes['location'] ?? null);
        return new self($location);
    }

    /**
     * Processes the incoming request.
     *
     * In the future, this method will check if a cached response exists for the incoming request
     * and return it if available. Currently, it returns the request unmodified.
     *
     * @param ProcessingRequest $request The incoming processing request.
     * @return ProcessingRequest The (potentially cached) processing request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        return $request;
    }

    /**
     * Caches the response to a file.
     *
     * @param Response $response The response to cache.
     * @return Response The original response.
     */
    public function processResponse(Response $response): Response
    {
        if ($response) {
            $path = $response->request()->requestPath();
            $cache = new FileCache($path, $this->location);
            $cache->store($response);
        }
        return $response;
    }
}
