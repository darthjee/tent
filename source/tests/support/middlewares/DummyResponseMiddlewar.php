<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\Middleware;
use Tent\Models\Response;

class DummyResponseMiddleware extends Middleware
{
    public function processResponse(Response $response): Response
    {
        $response->setHeader('X-Test', 'middleware');
        return $response;
    }

    /**
     * Builds a DummyResponseMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return Middleware The constructed DummyResponseMiddleware instance.
     */
    public static function build($attributes): DummyResponseMiddleware
    {
        return new self();
    }
}
