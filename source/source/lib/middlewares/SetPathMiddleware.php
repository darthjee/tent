<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to set or override uri in a ProcessingRequest.
 */
class SetPathMiddleware extends RequestMiddleware
{
    /**
     * @var array<string, string> Headers to set
     */
    private $headers;

    public function __construct(string $uri)
    {
    }

    /**
     * Builds a SetHeadersMiddleware using named parameters.
     *
     * Example:
     *   SetPathMiddleware::build(['uri' => '/index.html'])
     *
     * @return SetPathMiddleware
     */
    public static function build(array $attributes): SetPathMiddleware
    {
        return new self($attributes['uri'] ?? []);
    }

    public function process(ProcessingRequest $request): ProcessingRequest
    {
        return $request;
    }
}
