<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetPathMiddleware;
use Tent\Models\ProcessingRequest;

class SetPathMiddlewareTest extends TestCase
{
    public function testSetsRequestPath()
    {
        $request = new ProcessingRequest(['requestPath' => '/original/path']);
        $middleware = new SetPathMiddleware('/new/path');

        $result = $middleware->processRequest($request);
        $this->assertSame($request, $result);
        $this->assertEquals('/new/path', $result->requestPath());
    }

    public function testOverridesExistingPath()
    {
        $request = new ProcessingRequest(['requestPath' => '/old/path']);
        $middleware = new SetPathMiddleware('/overridden/path');

        $middleware->processRequest($request);
        $this->assertEquals('/overridden/path', $request->requestPath());
    }

    public function testSetsPathWhenNoneIsSet()
    {
        $request = new ProcessingRequest();
        $middleware = new SetPathMiddleware('/set/path');

        $middleware->processRequest($request);
        $this->assertEquals('/set/path', $request->requestPath());
    }
}
