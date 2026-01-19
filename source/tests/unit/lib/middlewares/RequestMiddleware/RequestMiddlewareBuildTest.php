<?php

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\RequestMiddleware;
use Tent\Tests\Support\Middlewares\DummyMiddleware;

class RequestMiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesMiddlewareInstanceFromClassAttribute()
    {
        $attributes = [
            'class' => DummyMiddleware::class,
            'foo' => 'bar',
        ];
        $middleware = RequestMiddleware::build($attributes);
        $this->assertInstanceOf(DummyMiddleware::class, $middleware);
    }

    public function testBuildCreatesMiddlewareInstanceFromStringClassName()
    {
        $attributes = [
            'class' => 'Tent\Tests\Support\Middlewares\DummyMiddleware',
            'foo' => 'bar',
        ];
        $middleware = RequestMiddleware::build($attributes);
        $this->assertInstanceOf(DummyMiddleware::class, $middleware);
    }

    public function testBuildCreatesMiddlewareInstanceFromOtherStringClassName()
    {
        $attributes = [
            'class' => "Tent\Middlewares\SetHeadersMiddleware",
            'foo' => 'bar',
            'headers' => [
                'X-Custom-Header' => 'value',
            ],
        ];
        $middleware = RequestMiddleware::build($attributes);
        $this->assertInstanceOf(
            \Tent\Middlewares\SetHeadersMiddleware::class,
            $middleware
        );
        $request = new \Tent\Models\ProcessingRequest([]);
        $modifiedRequest = $middleware->process($request);
        $this->assertEquals(
            'value',
            $modifiedRequest->headers()['X-Custom-Header']
        );
    }
}
