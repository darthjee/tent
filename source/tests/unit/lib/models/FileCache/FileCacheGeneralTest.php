<?php

namespace Tent\Tests\Models\FileCache;

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\Request;
use Tent\Utils\CacheFilePath;

class FileCacheGeneralTest extends TestCase
{
    private $basePath;
    private $path;
    private $fullPath;
    private $headers;
    private $request;
    private $meta;
    private $location;

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_' . uniqid();
        $this->path = 'some_file.txt';
        $this->fullPath = $this->basePath . '/' . $this->path;
        $this->headers = ['Content-Type' => 'text/plain'];
        $this->request = new Request(['requestPath' => $this->path, 'requestMethod' => 'GET']);
        $this->location = new FolderLocation($this->basePath);
        $this->meta = [
            'headers' => $this->headers,
            'httpCode' => 201
        ];

        $methodPath = $this->fullPath . '/GET';
        mkdir($methodPath, 0777, true);

        file_put_contents(CacheFilePath::path('body', $this->fullPath, 'GET', ''), 'Cached body content');
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, 'GET', ''), json_encode($this->meta));
    }

    public function tearDown(): void
    {
        @unlink(CacheFilePath::path('body', $this->fullPath, 'GET', ''));
        @unlink(CacheFilePath::path('meta', $this->fullPath, 'GET', ''));
        @rmdir($this->fullPath . '/GET');
        @rmdir($this->fullPath);
        @rmdir($this->basePath);
    }

    public function testContentReadsCacheBodyFile()
    {
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals('Cached body content', $cache->content());
    }

    public function testContentReadsCacheHeaders()
    {
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals($this->headers, $cache->headers());
    }

    public function testContentReadsCacheHttpCode()
    {
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals(201, $cache->httpCode());
    }

    public function testExistsReturnsTrueWhenBothFilesExist()
    {
        $cache = new FileCache($this->request, $this->location);
        $this->assertTrue($cache->exists());
    }

    public function testExistsReturnsFalseWhenBodyFileIsMissing()
    {
        @unlink(CacheFilePath::path('body', $this->fullPath, 'GET', ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertFalse($cache->exists());
    }

    public function testExistsReturnsFalseWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, 'GET', ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertFalse($cache->exists());
    }

    public function testHeadersReturnsEmptyArrayWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, 'GET', ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals([], $cache->headers());
    }

    public function testHttpCodeReturnsDefaultWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, 'GET', ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals(200, $cache->httpCode());
    }

    public function testHeadersReturnsEmptyArrayWhenMetaFileIsCorrupt()
    {
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, 'GET', ''), 'invalid json {]');
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals([], $cache->headers());
    }

    public function testHttpCodeReturnsDefaultWhenMetaFileIsCorrupt()
    {
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, 'GET', ''), 'invalid json {]');
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals(200, $cache->httpCode());
    }
}
