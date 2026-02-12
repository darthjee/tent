<?php

namespace Tent\Matchers;

use Tent\Models\Response;
use Tent\Models\ProcessingRequest;

/**
 * Filter that matches GET requests with successful (200) responses.
 *
 * This is an example of a Filter that checks both request and response criteria.
 * It only matches when:
 * - The request is a GET request
 * - The response has HTTP status code 200
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
 *         ['method' => 'GET', 'uri' => '/api/data', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
 *             'location' => './cache',
 *             'matchers' => [
 *                 [
 *                     'class' => 'Tent\\Matchers\\GetRequestSuccessFilter'
 *                 ]
 *             ]
 *         ]
 *     ]
 * ]);
 * ```
 */
class GetRequestSuccessFilter extends Filter
{
    /**
     * Checks if the request is a GET request.
     *
     * @param ProcessingRequest $request The request to check.
     * @return boolean True if the request is GET, false otherwise.
     */
    public function matchRequest(ProcessingRequest $request): bool
    {
        return $request->requestMethod() === 'GET';
    }

    /**
     * Checks if the response has HTTP status code 200.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response is 200 OK, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        return $response->httpCode() === 200;
    }

    /**
     * Builds a GetRequestSuccessFilter from the given attributes.
     *
     * @param array $attributes The attributes for building the filter (unused).
     * @return GetRequestSuccessFilter The constructed filter.
     */
    public static function build(array $attributes): GetRequestSuccessFilter
    {
        return new self();
    }
}
