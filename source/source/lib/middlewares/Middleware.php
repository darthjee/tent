<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

/**
 * Interface for request middlewares that can process or modify a ProcessingRequest.
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
