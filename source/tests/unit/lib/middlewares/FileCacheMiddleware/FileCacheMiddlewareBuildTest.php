<?php

namespace Tent\Tests\Middlewares;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;

class FileCacheMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(FileCacheMiddleware::class, $middleware);
    }
}
