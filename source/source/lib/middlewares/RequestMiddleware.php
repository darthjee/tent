<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Interface for request middlewares that can process or modify a ProcessingRequest.
 */
interface RequestMiddleware
{
    /**
     * Processes or modifies the given ProcessingRequest.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The (possibly modified) request.
     */
    public function process(ProcessingRequest $request): ProcessingRequest;
}
