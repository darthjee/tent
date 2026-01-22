<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\Middleware;
use Tent\Models\ProcessingRequest;

class DummyRequestMiddleware extends Middleware
{
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $request->setHeader('X-Test', 'middleware');
        return $request;
    }

    /**
     * Builds a DummyRequestMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return Middleware The constructed DummyRequestMiddleware instance.
     */
    public static function build($attributes): DummyRequestMiddleware
    {
        return new self();
    }
}
