<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\RequestMiddleware;
use Tent\Models\ProcessingRequest;

class QuickResponseMiddleware extends RequestMiddleware
{
    public function process(ProcessingRequest $request): ProcessingRequest
    {
        return $request;
    }

    /**
     * Builds a QuickResponseMiddleware instance.
     *
     * @param array $attributes Associative array of attributes (not used here).
     * @return RequestMiddleware The constructed QuickResponseMiddleware instance.
     */
    public static function build($attributes): QuickResponseMiddleware
    {
        return new self();
    }
}
