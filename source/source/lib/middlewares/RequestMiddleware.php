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
    public abstract function process(ProcessingRequest $request): ProcessingRequest;
}
