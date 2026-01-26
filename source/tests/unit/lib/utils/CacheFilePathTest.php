<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Utils\CacheFilePath;
use InvalidArgumentException;

class CacheFilePathTest extends TestCase
{
    public function testPathReturnsBodyCacheFile()
    {
        $this->assertEquals('/tmp/cache.body.txt', CacheFilePath::path('body', '/tmp', 'query'));
    }

    public function testPathReturnsHeadersCacheFile()
    {
        $this->assertEquals('/tmp/cache.headers.json', CacheFilePath::path('headers', '/tmp', 'query'));
    }

    public function testPathThrowsOnInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        CacheFilePath::path('invalid', '/tmp', 'query');
    }
}
