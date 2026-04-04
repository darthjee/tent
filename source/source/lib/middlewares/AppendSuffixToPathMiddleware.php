<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to append a fixed suffix to the current request path.
 *
 * Useful for translating frontend paths to backend paths that differ only
 * by a suffix (e.g. appending `.json` before forwarding to the backend).
 *
 * ## Usage Example (in configuration)
 *
 * ```php
 * use Tent\Configuration;
 *
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'default_proxy',
 *         'host' => 'http://api:80',
 *     ],
 *     'matchers' => [
 *         ['type' => 'begins_with', 'uri' => '/persons/'],
 *         ['type' => 'ends_with',   'uri' => '/photo'],
 *         ['method' => 'POST'],
 *     ],
 *     'middlewares' => [
 *         [
 *             'class'  => 'Tent\\Middlewares\\AppendSuffixToPathMiddleware',
 *             'suffix' => '.json',
 *         ],
 *     ],
 * ]);
 * ```
 *
 * The middleware can also be instantiated directly:
 *
 * ```php
 * $middleware = AppendSuffixToPathMiddleware::build(['suffix' => '.json']);
 * ```
 */
class AppendSuffixToPathMiddleware extends Middleware
{
    /**
     * @var string The suffix to append to the request path.
     */
    private $suffix;

    /**
     * Constructor.
     *
     * @param string $suffix The suffix to append to the request path.
     */
    public function __construct(string $suffix)
    {
        $this->suffix = $suffix;
    }

    /**
     * Builds an AppendSuffixToPathMiddleware using named parameters.
     *
     * Example:
     *   AppendSuffixToPathMiddleware::build(['suffix' => '.json'])
     *
     * @param array $attributes Associative array with key 'suffix' (string).
     * @return AppendSuffixToPathMiddleware
     */
    public static function build(array $attributes): self
    {
        return new self($attributes['suffix'] ?? '');
    }

    /**
     * Appends the configured suffix to the request path.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The modified request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $request->setRequestPath($request->requestPath() . $this->suffix);
        return $request;
    }
}
