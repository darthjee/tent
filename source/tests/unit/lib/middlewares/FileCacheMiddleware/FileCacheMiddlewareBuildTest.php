<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;

class FileCacheMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(FileCacheMiddleware::class, $middleware);
    }
}
