<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
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

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_' . uniqid();
        $this->path = 'some_file.txt';
        $this->fullPath = $this->basePath . '/' . $this->path;
        $this->headers = ['Content-Type' => 'text/plain'];
        $this->request = new Request(['requestPath' => $this->path]);
        $this->meta = ['headers' => $this->headers];

        mkdir($this->fullPath, 0777, true);

        file_put_contents(CacheFilePath::path('body', $this->fullPath, ''), 'Cached body content');
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, ''), json_encode($this->meta));
    }

    public function tearDown(): void
    {
        @unlink(CacheFilePath::path('body', $this->fullPath, ''));
        @unlink(CacheFilePath::path('meta', $this->fullPath, ''));
        @rmdir($this->fullPath);
        @rmdir($this->basePath);
    }

    public function testContentReadsCacheBodyFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->request, $location);
        $this->assertEquals('Cached body content', $cache->content());
    }

    public function testContentReadsCacheHeadersFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->request, $location);
        $this->assertEquals($this->headers, $cache->headers());
    }

    public function testExistsReturnsTrueWhenBothFilesExist()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->request, $location);
        $this->assertTrue($cache->exists());
    }

    public function testExistsReturnsFalseWhenBodyFileIsMissing()
    {
        @unlink(CacheFilePath::path('body', $this->fullPath, ''));
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->request, $location);
        $this->assertFalse($cache->exists());
    }

    public function testExistsReturnsFalseWhenMetaFileIsMissing()
    {
        @unlink(CacheFilePath::path('meta', $this->fullPath, ''));
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->request, $location);
        $this->assertFalse($cache->exists());
    }
}
