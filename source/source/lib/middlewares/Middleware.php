<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

/**
 * Base class for Tent middlewares that can process or modify requests and responses.
 *
 * ## Example: Request Middleware (prepend /api/ to path)
 *
 * ```php
 * namespace Tent\Middlewares;
 *
 * use Tent\Models\ProcessingRequest;
 *
 * class PrependApiPathMiddleware extends Middleware
 * {
 *     private string $prepend;
 *
 *     public function __construct(string $prepend = '/api')
 *     {
 *         $this->prepend = $prepend;
 *     }
 *
 *     public static function build(array $attributes): self
 *     {
 *         return new self($attributes['prepend'] ?? '/api');
 *     }
 *
 *     public function processRequest(ProcessingRequest $request): ProcessingRequest
 *     {
 *         $path = $request->requestPath();
 *         if (strpos($path, $this->prepend . '/') !== 0) {
 *             $request->setRequestPath($this->prepend . $path);
 *         }
 *         return $request;
 *     }
 *
 *     // Optionally implement processResponse if needed
 * }
 * ```
 *
 * ## Example: Response Middleware (simple header)
 *
 * ```php
 * namespace Tent\Middlewares;
 *
 * use Tent\Models\Response;
 *
 * class AddPoweredByHeaderMiddleware extends Middleware
 * {
 *     public function processResponse(Response $response): Response
 *     {
 *         $response->setHeader('X-Powered-By', 'Tent');
 *         return $response;
 *     }
 * }
 * ```
 *
 * ## Usage in configuration
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
 *             'class' => 'Tent\\Middlewares\\PrependApiPathMiddleware',
 *             'prepend' => '/api'
 *         ],
 *         [
 *             'class' => 'Tent\\Middlewares\\AddPoweredByHeaderMiddleware'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * Middlewares can override either processRequest, processResponse, or both.
 */
abstract class Middleware
{
    /**
     * Processes or modifies the given ProcessingRequest.
     *
     * This should be overridden by subclasses to implement specific middleware logic.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The (possibly modified) request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        return $request;
    }

    /**
     * Processes or modifies the given Response.
     *
     * This should be overridden by subclasses to implement specific middleware logic.
     *
     * @param Response $response The response to process.
     * @return Response The (possibly modified) response.
     */
    public function processResponse(Response $response): Response
    {
        return $response;
    }

    /**
     * Builds a Middleware instance from given attributes.
     *
     * @param array $attributes Associative array of attributes, must include 'class' key.
     * @return Middleware The constructed middleware instance.
     */
    public static function build(array $attributes): Middleware
    {
        $class = $attributes['class'];

        return $class::build($attributes);
    }
}
