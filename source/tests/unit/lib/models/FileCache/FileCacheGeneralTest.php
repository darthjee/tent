<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\Request;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

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
        $this->fullPath = $this->basePath . '/' . $this->path . '/GET';
        $this->headers = ['Content-Type' => 'text/plain'];
        $this->request = new Request(['requestPath' => $this->path, 'requestMethod' => 'GET']);
        $this->location = new FolderLocation($this->basePath);
        $this->meta = [
            'headers' => $this->headers,
            'httpCode' => 201
        ];

        mkdir($this->fullPath, 0777, true);

        file_put_contents(CacheFilePath::path('body', $this->fullPath, ''), 'Cached body content');
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, ''), json_encode($this->meta));
    }

    public function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->basePath);
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
        @unlink(CacheFilePath::path('body', $this->fullPath, ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertFalse($cache->exists());
    }

    public function testExistsReturnsFalseWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertFalse($cache->exists());
    }

    public function testHeadersReturnsEmptyArrayWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals([], $cache->headers());
    }

    public function testHttpCodeReturnsDefaultWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, ''));
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals(200, $cache->httpCode());
    }

    public function testHeadersReturnsEmptyArrayWhenMetaFileIsCorrupt()
    {
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, ''), 'invalid json {]');
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals([], $cache->headers());
    }

    public function testHttpCodeReturnsDefaultWhenMetaFileIsCorrupt()
    {
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, ''), 'invalid json {]');
        $cache = new FileCache($this->request, $this->location);
        $this->assertEquals(200, $cache->httpCode());
    }

    public function testHeadersIncludesCacheTimeWhenTimestampPresent()
    {
        $timestamp = mktime(12, 30, 0, 6, 15, 2025);
        $metaWithTimestamp = array_merge($this->meta, ['timestamp' => $timestamp]);
        file_put_contents(
            CacheFilePath::path('meta', $this->fullPath, ''),
            json_encode($metaWithTimestamp)
        );

        $cache = new FileCache($this->request, $this->location);
        $headers = $cache->headers();

        $this->assertContains('X-Cache-Time: ' . gmdate('Y-m-d H:i:s', $timestamp), $headers);
    }

    public function testHeadersDoesNotIncludeCacheTimeWhenTimestampMissing()
    {
        $cache = new FileCache($this->request, $this->location);
        $headers = $cache->headers();

        foreach ($headers as $header) {
            $this->assertStringNotContainsString('X-CACHE-TIME', $header);
        }
    }
}
