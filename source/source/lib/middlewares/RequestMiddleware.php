<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Interface for request middlewares that can process or modify a ProcessingRequest.
 */
abstract class RequestMiddleware
{
    /**
     * Processes or modifies the given ProcessingRequest.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The (possibly modified) request.
     */
    abstract public function process(ProcessingRequest $request): ProcessingRequest;

    /**
     * Builds a RequestMiddleware instance from given attributes.
     *
     * @param array $attributes Associative array of attributes, must include 'class' key.
     * @return RequestMiddleware The constructed middleware instance.
     */
    public static function build(array $attributes): RequestMiddleware
    {
        $class = $attributes['class'];

        return $class::build($attributes);
    }
}
