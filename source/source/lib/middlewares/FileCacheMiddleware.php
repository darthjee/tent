<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Matchers\ResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Service\ResponseCacher;

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
      'Deprecation warning: The "httpCodes" attribute is deprecated. Use "matchers" instead.';

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
     * @param array|null     $requestMethods Array of HTTP request methods to cache. Defaults to ['GET'].
     * @param array          $matchers       Array of custom matchers for cacheability.
     */
    public function __construct(FolderLocation $location, ?array $requestMethods = null, array $matchers = [])
    {
        $this->location = $location;
        $this->requestMethods = $requestMethods ?? ['GET'];

        $this->matchers = $matchers;
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
        $requestMethods = $attributes['requestMethods'] ?? null;

        if (isset($attributes['matchers'])) {
            if (isset($attributes['httpCodes'])) {
                trigger_error(self::DEPRECATION_HTTP_CODES_MSG, E_USER_DEPRECATED);
            }
            $matchers = ResponseMatcher::buildMatchers($attributes['matchers']);
        } elseif (isset($attributes['httpCodes'])) {
            trigger_error(self::DEPRECATION_HTTP_CODES_MSG, E_USER_DEPRECATED);
            $httpCodes = $attributes['httpCodes'] ?? [200];
            $matchers = [new StatusCodeMatcher($httpCodes)];
        } else {
            $matchers = [new StatusCodeMatcher([200])];
        }

        return new self($location, $requestMethods, $matchers);
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

    /**
     * Check if the response is cacheable based on the configured matchers.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response is storable, false otherwise.
     */
    private function isCacheable(Response $response): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->match($response)) {
                return false;
            }
        }
        return true;
    }
}
