<?php

namespace Tent\Tests\Middlewares;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Models\FileCache;

class FileCacheMiddlewareProcessResponseTest extends TestCase
{
    private $cacheDir;
    private $location;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cacheDir . '/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testProcessResponseStoresCache()
    {
        $path = '/file.txt';
        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $request = new ProcessingRequest(['requestPath' => $path]);

        $response = new Response([
            'body' => 'cached body', 'httpCode' => 200, 'headers' => $headers,
            'request' => $request
        ]);

        $middleware = new FileCacheMiddleware($this->location);
        $middleware->processResponse($response);

        $cache = new FileCache($path, $this->location);
        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals($headers, $cache->headers());
    }
}
