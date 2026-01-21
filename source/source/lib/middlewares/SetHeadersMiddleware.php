<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to set or override headers in a ProcessingRequest.
 *
 * ## Usage Example (in configuration)
 *
 * ```php
 * use Tent\Configuration;
 *
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
 *             'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
 *             'headers' => [
 *                 'Host' => 'backend.local'
 *             ]
 *         ]
 *     ]
 * ]);
 * ```
 *
 * The middleware can also be instantiated directly:
 *
 * ```php
 * $middleware = SetHeadersMiddleware::build([
 *     'headers' => ['Host' => 'backend.local']
 * ]);
 * ```
 */
class SetHeadersMiddleware extends RequestMiddleware
{
    /**
     * @var array<string, string> Headers to set
     */
    private $headers;

    /**
     * @param array<string, string> $headers Associative array of headers
     *   to set (e.g., ['Host' => 'some_host']).
     */
    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Builds a SetHeadersMiddleware using named parameters.
     *
     * Example:
     *   SetHeadersMiddleware::build(['headers' => ['Host' => 'some_host']])
     *
     * @param array $attributes Associative array with key 'headers' (array).
     * @return SetHeadersMiddleware
     */
    public static function build(array $attributes): SetHeadersMiddleware
    {
        return new self($attributes['headers'] ?? []);
    }

    /**
     * Sets or overrides headers in the ProcessingRequest.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The modified request
     */
    public function process(ProcessingRequest $request): ProcessingRequest
    {
        foreach ($this->headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        return $request;
    }
}
