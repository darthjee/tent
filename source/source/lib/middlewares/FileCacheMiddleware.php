<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Matchers\Filter;
use Tent\Matchers\ResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Service\ResponseCacher;
use Tent\Utils\Logger;

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
 *             'httpCodes' => [200], // or ["2xx"] for all 2xx codes
 *             // 'requestMethods' => ['GET'] // optional, defaults to ['GET']
 *         ]
 *     ]
 * ]);
 * ```
 *
 * - `location`: Directory where cached responses are stored (required).
 * - `httpCodes`: Array of HTTP status codes to cache (e.g., [200], ["2xx"]).
 * - `requestMethods`: Array of HTTP methods to cache (default: ['GET']).
 *
 * This middleware will cache responses matching the specified codes and
 * methods, and serve them from cache on subsequent requests.
 */
class FileCacheMiddleware extends Middleware
{
    /**
     * Deprecation warning message for httpCodes attribute.
     */
    private const DEPRECATION_HTTP_CODES_MSG =
      'Deprecation warning: The "httpCodes" attribute is deprecated. Use "matchers" with Filter instead.';

    /**
     * @var FolderLocation The base folder location for caching.
     */
    private FolderLocation $location;

    /**
     * @var array The list of HTTP request methods to cache.
     */
    private array $requestMethods;

    /**
     * @var array The list of filters to determine cacheability.
     */
    private array $filters;

    /**
     * Constructs a FileCacheMiddleware instance.
     *
     * @param FolderLocation $location       The base folder location for caching.
     * @param array|null     $requestMethods Array of HTTP request methods to cache. Defaults to ['GET'].
     * @param array          $filters        Array of Filter instances for cacheability.
     */
    public function __construct(FolderLocation $location, ?array $requestMethods = null, array $filters = [])
    {
        $this->location = $location;
        $this->requestMethods = $requestMethods ?? ['GET'];

        $this->filters = $filters;
    }

    /**
     * Builds a FileCacheMiddleware instance from the given attributes.
     *
     * @param array $attributes The attributes to build the middleware.
     * @return FileCacheMiddleware The constructed FileCacheMiddleware instance.
     * @deprecated The 'httpCodes' attribute is deprecated. Use 'matchers' with Filter instead.
     */
    public static function build(array $attributes): FileCacheMiddleware
    {
        $location = new FolderLocation($attributes['location']);
        $requestMethods = $attributes['requestMethods'] ?? null;

        if (isset($attributes['httpCodes'])) {
            Logger::deprecate(self::DEPRECATION_HTTP_CODES_MSG);
        }

        if (isset($attributes['matchers'])) {
            // Use new Filter::buildFilters for new configuration style
            $filters = Filter::buildFilters($attributes['matchers']);
        } elseif (isset($attributes['httpCodes'])) {
            // Backward compatibility: use deprecated ResponseMatcher::buildMatchers
            $httpCodes = $attributes['httpCodes'] ?? [200];
            $filters = [new StatusCodeMatcher($httpCodes)];
        } else {
            $filters = [new StatusCodeMatcher([200])];
        }

        return new self($location, $requestMethods, $filters);
    }

    /**
     * Processes the incoming request.
     *
     * Only attempts to read from cache if the request method is included in the configured
     * requestMethods filter AND all filters match the request. If the filters don't match,
     * the request is returned unmodified.
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

        if (!$this->matchesRequest($request)) {
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
     * Only stores the response if it matches all configured filters.
     *
     * @param Response $response The response to cache.
     * @return Response The original response.
     */
    public function processResponse(Response $response): Response
    {
        if ($this->isCacheable($response)) {
            $cache = new FileCache($response->request(), $this->location);
            (new ResponseCacher($cache, $response))->process();
        }
        return $response;
    }

    /**
     * Check if the request matches all configured filters.
     *
     * @param ProcessingRequest $request The request to check.
     * @return boolean True if the request matches all filters, false otherwise.
     */
    private function matchesRequest(ProcessingRequest $request): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->matchRequest($request)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the response is cacheable based on the configured filters.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response is storable, false otherwise.
     */
    private function isCacheable(Response $response): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->matchResponse($response)) {
                return false;
            }
        }
        return true;
    }
}
