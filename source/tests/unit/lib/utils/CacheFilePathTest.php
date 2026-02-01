<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Utils\CacheFilePath;
use InvalidArgumentException;

class CacheFilePathTest extends TestCase
{
    public function testPathReturnsBodyCacheFile()
    {
        $expected = '/tmp/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.body.txt';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'query'));
    }

    public function testPathReturnsHeadersCacheFile()
    {
        $expected = '/tmp/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.headers.json';
        $this->assertEquals($expected, CacheFilePath::path('headers', '/tmp', 'query'));
    }

    public function testPathReturnsMetaCacheFile()
    {
        $expected = '/tmp/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.meta.json';
        $this->assertEquals($expected, CacheFilePath::path('meta', '/tmp', 'query'));
    }

    public function testPathThrowsOnInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        CacheFilePath::path('invalid', '/tmp', 'query');
    }
}
