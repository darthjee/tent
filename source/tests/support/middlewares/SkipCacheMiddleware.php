<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\Middleware;
use Tent\Models\ProcessingRequest;

class SkipCacheMiddleware extends Middleware
{
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $request->skipCache();
        return $request;
    }

    /**
     * Builds a SkipCacheMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return Middleware The constructed SkipCacheMiddleware instance.
     */
    public static function build($attributes): SkipCacheMiddleware
    {
        return new self();
    }
}
