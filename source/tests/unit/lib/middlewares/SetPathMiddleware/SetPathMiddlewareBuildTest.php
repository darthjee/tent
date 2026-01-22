<?php

namespace Tent\Tests\Middlewares\SetPathMiddleware;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetPathMiddleware;
use Tent\Models\ProcessingRequest;

class SetPathMiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesInstanceWithPath()
    {
        $attributes = [
            'path' => '/custom/path',
        ];
        $middleware = SetPathMiddleware::build($attributes);
        $this->assertInstanceOf(SetPathMiddleware::class, $middleware);
    }

    public function testBuildAndProcessing()
    {
        $attributes = [
            'path' => '/custom/path',
        ];
        $middleware = SetPathMiddleware::build($attributes);
        $request = new ProcessingRequest(['requestPath' => '/original/path']);
        $modifiedRequest = $middleware->processRequest($request);

        $this->assertEquals('/custom/path', $modifiedRequest->requestPath());
    }

    public function testBuildDefaultsToSlash()
    {
        $middleware = SetPathMiddleware::build([]);
        $request = new ProcessingRequest(['requestPath' => '/something']);
        $middleware->processRequest($request);
        $this->assertEquals('/', $request->requestPath());
    }
}
