<?php

namespace Tent\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Tent\Utils\CacheFilePath;
use InvalidArgumentException;

class CacheFilePathTest extends TestCase
{
    public function testPathReturnsBodyCacheFile()
    {
        $expected = '/tmp/GET/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'GET', 'query'));
    }

    public function testPathReturnsMetaCacheFile()
    {
        $expected = '/tmp/GET/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.meta.json';
        $this->assertEquals($expected, CacheFilePath::path('meta', '/tmp', 'GET', 'query'));
    }

    public function testPathThrowsOnInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        CacheFilePath::path('invalid', '/tmp', 'GET', 'query');
    }

    public function testPathWithPostMethod()
    {
        $expected = '/tmp/POST/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'POST', 'query'));
    }

    public function testPathWithPutMethod()
    {
        $expected = '/tmp/PUT/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'PUT', 'query'));
    }

    public function testPathWithDeleteMethod()
    {
        $expected = '/tmp/DELETE/a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'DELETE', 'query'));
    }

    public function testDifferentMethodsGenerateDifferentPaths()
    {
        $getPath = CacheFilePath::path('body', '/tmp', 'GET', 'query');
        $postPath = CacheFilePath::path('body', '/tmp', 'POST', 'query');
        $this->assertNotEquals($getPath, $postPath);
    }
}
