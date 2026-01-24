<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Location;

class FileCacheMiddleware extends Middleware
{
    private Location $location;

    public function __construct(Location $location)
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
