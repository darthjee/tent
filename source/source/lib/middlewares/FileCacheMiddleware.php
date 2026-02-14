<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Models\Response;
use Tent\Service\ResponseContentReader;
use Tent\Matchers\RequestResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;
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
 *             'matchers' => [
 *                 [
 *                     'class' => 'Tent\\Matchers\\StatusCodeMatcher',
 *                     'httpCodes' => [200] // or ["2xx"] for all 2xx codes
 *                 ],
 *                 [
 *                     'class' => 'Tent\\Matchers\\RequestMethodMatcher',
 *                     'requestMethods' => ['GET', 'POST']
 *                 ]
 *             ]
 *         ]
 *     ]
 * ]);
 * ```
 *
 * - `location`: Directory where cached responses are stored (required).
 * - `matchers`: Array of matcher configurations to determine cacheability.
 * - `httpCodes`: (DEPRECATED) Array of HTTP status codes to cache. Use `matchers` instead.
 * - `requestMethods`: (DEPRECATED) Array of HTTP methods to cache. Use `matchers` instead.
 *
 * This middleware will cache responses matching all configured matchers,
 * and serve them from cache on subsequent requests.
 */
class FileCacheMiddleware extends Middleware
{
    /**
     * Deprecation warning message for httpCodes attribute.
     */
    private const DEPRECATION_HTTP_CODES_MSG =
      'Deprecation warning: The "httpCodes" attribute is deprecated. Use "matchers" instead.';

    /**
     * Deprecation warning message for requestMethods attribute.
     */
    private const DEPRECATION_REQUEST_METHODS_MSG =
      'Deprecation warning: The "requestMethods" attribute is deprecated. Use "matchers" instead.';

    /**
     * @var FolderLocation The base folder location for caching.
     */
    private FolderLocation $location;

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
    public function __construct(FolderLocation $location, array $matchers = [])
    {
        $this->location = $location;

        $this->matchers = $matchers;
    }

    /**
     * Builds a FileCacheMiddleware instance from the given attributes.
     *
     * @param array $attributes The attributes to build the middleware.
     * @return FileCacheMiddleware The constructed FileCacheMiddleware instance.
     * @deprecated The 'httpCodes' attribute is deprecated. Use 'matchers' instead.
     */
    public static function build(array $attributes): FileCacheMiddleware
    {
        $location = new FolderLocation($attributes['location']);

        if (isset($attributes['httpCodes'])) {
            Logger::deprecate(self::DEPRECATION_HTTP_CODES_MSG);
        }

        if (isset($attributes['requestMethods'])) {
            Logger::deprecate(self::DEPRECATION_REQUEST_METHODS_MSG);
        }

        if (isset($attributes['matchers'])) {
            $matchers = ResponseMatcher::buildMatchers($attributes['matchers']);
        } else {
            if (isset($attributes['httpCodes'])) {
                $httpCodes = $attributes['httpCodes'] ?? [200];
                $matchers = [new StatusCodeMatcher($httpCodes)];
            } else {
                $matchers = [new StatusCodeMatcher([200])];
            }

            if (isset($attributes['requestMethods'])) {
                $requestMethods = $attributes['requestMethods'] ?? ['GET'];
                $matchers[] = new RequestMethodMatcher($requestMethods);
            } else {
                $matchers[] = new RequestMethodMatcher(['GET']);
            }
        }

        return new self($location, $matchers);
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
        if (!in_array($request->requestMethod(), $this->requestMethods, true)) {
            return $request;
        }

        foreach ($this->matchers as $matcher) {
            if (!$matcher->matchRequest($request)) {
                return $request;
            }
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
}
