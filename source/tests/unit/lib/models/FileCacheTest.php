<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\FolderLocation;

class FileCacheTest extends TestCase
{
    private $basePath;
    private $path;
    private $fullPath;

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_' . uniqid();
        $this->path = 'some_file.txt';
        $this->fullPath = $this->basePath . '/' . $this->path;

        mkdir($this->fullPath, 0777, true);

        file_put_contents($this->fullPath . '/cache.body.txt', 'Cached body content');
    }

    public function tearDown(): void
    {
        @unlink($this->fullPath . '/cache.body.txt');
        @unlink($this->fullPath . '/cache.headers.json');
        @rmdir($this->basePath);
    }

    public function testContentReadsCacheBodyFile()
    {
        $location = new FolderLocation($this->basePath);
        $cache = new FileCache($this->path, $location);
        $this->assertEquals('Cached body content', $cache->content());
    }
}
