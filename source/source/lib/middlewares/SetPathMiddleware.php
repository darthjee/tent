<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to set or override the request path in a ProcessingRequest.
 *
 * This middleware allows you to change the path portion of the request URL
 * (e.g., /index.html) before further processing.
 *
 * ## Usage Example (in configuration)
 *
 * ```php
 * use Tent\Configuration;
 *
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/static/'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\\Middlewares\\SetPathMiddleware',
 *             'path' => '/index.html'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * The middleware can also be instantiated directly:
 *
 * ```php
 * $middleware = SetPathMiddleware::build(['path' => '/index.html']);
 * ```
 */
class SetPathMiddleware extends Middleware
{
    /**
     * @var string The path to set on the request (should start with '/').
     */
    private $path;

    /**
     * Constructor.
     *
     * @param string $path The path to set (should start with '/').
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Builds a SetPathMiddleware using named parameters.
     *
     * Example:
     *   SetPathMiddleware::build(['path' => '/new/path'])
     *
     * @param array $attributes Associative array with key 'path' (string).
     * @return SetPathMiddleware
     */
    public static function build(array $attributes): self
    {
        return new self($attributes['path'] ?? '/');
    }

    /**
     * Sets or overrides the request path in the ProcessingRequest.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The modified request
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $request->setRequestPath($this->path);
        return $request;
    }
}
