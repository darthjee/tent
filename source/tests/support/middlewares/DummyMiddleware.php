<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\RequestMiddleware;
use Tent\Models\ProcessingRequest;

class DummyMiddleware extends RequestMiddleware
{
    public function process(ProcessingRequest $request): ProcessingRequest
    {
        $request->setHeader('X-Test', 'middleware');
        return $request;
    }

    /**
     * Builds a DummyMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return RequestMiddleware The constructed DummyMiddleware instance.
     */
    public static function build($attributes): DummyMiddleware
    {
        return new self();
    }
}
