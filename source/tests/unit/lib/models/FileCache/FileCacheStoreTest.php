<?php

namespace Tent\Tests\Models\FileCache;

use PHPUnit\Framework\TestCase;
use Tent\Models\FileCache;
use Tent\Models\Response;
use Tent\Models\Request;
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
        array_map('unlink', glob($this->cacheDir . '/*/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testStoreBodyAndHeaders()
    {
        $path = '/path/file.txt';
        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $request = new Request([]);
        $response = new Response([
            'body' => 'cached body', 'httpCode' => 200, 'headers' => $headers, 'request' => $request
        ]);

        $cache = new FileCache($path, $this->location);

        $cache->store($response);

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $cache->headers());
    }

    public function testStoreCreatesDirectories()
    {
        $path = '/nested_dir/file.txt';
        $request = new Request([]);
        $response = new Response([
            'body' => 'nested body', 'httpCode' => 200, 'headers' => [], 'request' => $request
        ]);

        $cache = new FileCache($path, $this->location);

        $cache->store($response);

        $fullPath = $this->cacheDir . '/nested_dir/file.txt';
        $this->assertTrue(is_dir($fullPath));
    }

    public function testStoreWithExistingDirectory()
    {
        $path = '/nested_dir/file.txt';
        $fullPath = $this->cacheDir . '/nested_dir/file.txt';
        mkdir($fullPath, 0777, true);

        $request = new Request([]);
        $response = new Response([
            'body' => 'some body', 'httpCode' => 200, 'headers' => [], 'request' => $request
        ]);

        $cache = new FileCache($path, $this->location);

        $cache->store($response);

        $this->assertTrue(is_dir($fullPath));
        $this->assertTrue($cache->exists());
        $this->assertEquals('some body', $cache->content());
        $this->assertEquals([], $cache->headers());
    }
}
