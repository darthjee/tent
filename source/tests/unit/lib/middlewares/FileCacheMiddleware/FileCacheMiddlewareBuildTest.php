<?php

namespace Tent\Tests\Middlewares;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;

class FileCacheMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $location = new FolderLocation('/tmp/cache');
        $middleware = FileCacheMiddleware::build(['location' => $location]);

        $this->assertInstanceOf(FileCacheMiddleware::class, $middleware);
    }
}
