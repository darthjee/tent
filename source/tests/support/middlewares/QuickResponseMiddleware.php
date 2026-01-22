<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\Middleware;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

class QuickResponseMiddleware extends Middleware
{
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $response = new Response('Quick Response', 200, ['Content-Type: text/plain']);
        $request->setResponse($response);

        return $request;
    }

    /**
     * Builds a QuickResponseMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return Middleware The constructed QuickResponseMiddleware instance.
     */
    public static function build($attributes): QuickResponseMiddleware
    {
        return new self();
    }
}
