<?php

namespace Tent\Tests\Middlewares\Middleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\Middleware;
use Tent\Tests\Support\Middlewares\DummyRequestMiddleware;

class MiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesMiddlewareInstanceFromClassAttribute()
    {
        $attributes = [
            'class' => DummyRequestMiddleware::class,
            'foo' => 'bar',
        ];
        $middleware = Middleware::build($attributes);
        $this->assertInstanceOf(DummyRequestMiddleware::class, $middleware);
    }

    public function testBuildCreatesMiddlewareInstanceFromStringClassName()
    {
        $attributes = [
            'class' => 'Tent\Tests\Support\Middlewares\DummyRequestMiddleware',
            'foo' => 'bar',
        ];
        $middleware = Middleware::build($attributes);
        $this->assertInstanceOf(DummyRequestMiddleware::class, $middleware);
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
        $middleware = Middleware::build($attributes);
        $this->assertInstanceOf(
            \Tent\Middlewares\SetHeadersMiddleware::class,
            $middleware
        );
        $request = new \Tent\Models\ProcessingRequest([]);
        $modifiedRequest = $middleware->processRequest($request);
        $this->assertEquals(
            'value',
            $modifiedRequest->headers()['X-Custom-Header']
        );
    }
}
