<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;

class FileCacheMiddleware extends Middleware
{
    private FolderLocation $location;

    public function __construct(FolderLocation $location)
    {
        $this->location = $location;
    }

    public static function build(array $attributes): FileCacheMiddleware
    {
        return new self($attributes['location'] ?? null);
    }

    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        return $request;
    }
}
