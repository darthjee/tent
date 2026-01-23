<?php

namespace Tent\Tests\Models\FileCache;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\FolderLocation;

class FileCacheStoreTest extends TestCase
{
    private $cacheDir;
    private $location;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testStoreBodyAndHeaders()
    {
        $cache = new FileCache('', $this->location);
        $bodyPath = $this->cacheDir . '/cache.body.txt';
        $headersPath = $this->cacheDir . '/cache.headers.json';

        // Simulate storing
        file_put_contents($bodyPath, 'cached body');
        file_put_contents($headersPath, json_encode(['Content-Type: text/plain', 'Content-Length: 11']));

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $cache->headers());
    }

    public function testStoreMissingFiles()
    {
        $cache = new FileCache('', $this->location);
        $this->assertFalse($cache->exists());
    }
}
