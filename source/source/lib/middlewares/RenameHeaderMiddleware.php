<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to rename a request header in a ProcessingRequest.
 *
 * Reads the value of the `from` header and sets it under the `to` header,
 * then removes the original `from` header. If the `from` header is not present,
 * the request is left unchanged.
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
 *             'class' => 'Tent\\Middlewares\\RenameHeaderMiddleware',
 *             'from' => 'Host',
 *             'to'   => 'X-Original-Host'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * The middleware can also be instantiated directly:
 *
 * ```php
 * $middleware = RenameHeaderMiddleware::build([
 *     'from' => 'Host',
 *     'to'   => 'X-Original-Host'
 * ]);
 * ```
 */
class RenameHeaderMiddleware extends Middleware
{
    /**
     * @var string The header name to read from.
     */
    private $from;

    /**
     * @var string The header name to write to.
     */
    private $to;

    /**
     * @param string $from The original header name.
     * @param string $to   The new header name.
     */
    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to   = $to;
    }

    /**
     * Builds a RenameHeaderMiddleware using named parameters.
     *
     * Example:
     *   RenameHeaderMiddleware::build(['from' => 'Host', 'to' => 'X-Original-Host'])
     *
     * @param array $attributes Associative array with keys 'from' and 'to' (strings).
     * @return RenameHeaderMiddleware
     */
    public static function build(array $attributes): RenameHeaderMiddleware
    {
        return new self($attributes['from'], $attributes['to']);
    }

    /**
     * Renames a header in the ProcessingRequest.
     *
     * Copies the value of the `from` header to the `to` header and removes
     * the original `from` header. Does nothing if the `from` header is absent.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The modified request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $headers = $request->headers();

        if (array_key_exists($this->from, $headers)) {
            $request->setHeader($this->to, $headers[$this->from]);
            $request->removeHeader($this->from);
        }

        return $request;
    }
}
