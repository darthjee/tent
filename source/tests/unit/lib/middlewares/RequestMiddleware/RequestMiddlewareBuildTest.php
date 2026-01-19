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
}