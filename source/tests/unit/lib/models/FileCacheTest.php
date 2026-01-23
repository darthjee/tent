<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\FolderLocation;

class FileCacheTest extends TestCase
{
    private $basePath;
    private $path;

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_' . uniqid();
        $this->path = 'some_file.txt';

        mkdir($this->basePath);
        file_put_contents($this->basePath . '/cache.body.txt', 'Cached body content');
    }

    public function tearDown(): void
    {
        @unlink($this->basePath . '/cache.body.txt');
        @unlink($this->basePath . '/cache.headers.json');
        @rmdir($this->basePath);
    }

    public function testContentReadsCacheBodyFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertEquals('Cached body content', $cache->content());
    }
}
