<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\FolderLocation;

class FileCacheGeneralTest extends TestCase
{
    private $basePath;
    private $path;
    private $fullPath;
    private $headers;

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_' . uniqid();
        $this->path = 'some_file.txt';
        $this->fullPath = $this->basePath . '/' . $this->path;
        $this->headers = ['Content-Type' => 'text/plain'];

        mkdir($this->fullPath, 0777, true);

        file_put_contents($this->fullPath . '/cache.body.txt', 'Cached body content');
        file_put_contents($this->fullPath . '/cache.headers.json', json_encode($this->headers));
    }

    public function tearDown(): void
    {
        @unlink($this->fullPath . '/cache.body.txt');
        @unlink($this->fullPath . '/cache.headers.json');
        @rmdir($this->fullPath);
        @rmdir($this->basePath);
    }

    public function testContentReadsCacheBodyFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertEquals('Cached body content', $cache->content());
    }

    public function testContentReadsCacheHeadersFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertEquals($this->headers, $cache->headers());
    }

    public function testExistsReturnsTrueWhenBothFilesExist()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertTrue($cache->exists());
    }

    public function testExistsReturnsFalseWhenBodyFileIsMissing()
    {
        @unlink($this->fullPath . '/cache.body.txt');
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertFalse($cache->exists());
    }

    public function testExistsReturnsFalseWhenHeadersFileIsMissing()
    {
        @unlink($this->fullPath . '/cache.headers.json');
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertFalse($cache->exists());
    }
}
