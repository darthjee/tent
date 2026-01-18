<?php

namespace Tent\Tests\Support\Middlewares;

use Tent\Middlewares\RequestMiddleware;
use Tent\Models\ProcessingRequest;

class DummyMiddleware implements RequestMiddleware
{
    public function process(ProcessingRequest $request): ProcessingRequest
    {
        $request->setHeader('X-Test', 'middleware');
        return $request;
    }
}