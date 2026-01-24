<?php

namespace Tent\Tests\Models\FileCache;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\Response;
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
        array_map('unlink', glob($this->cacheDir . '/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testStoreBodyAndHeaders()
    {
        $path = '/file.txt';
        $bodyPath = $this->cacheDir . $path . '/body.txt';
        $headersPath = $this->cacheDir . $path . '/headers.json';

        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $response = new Response('cached body', 200, $headers);

        $cache = new FileCache($path, $this->location);

        $cache->store($response);

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $cache->headers());
    }
}
