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
    private $headers;
    private $cache;
    private $request;
    private $path;

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
        $response = $this->buildResponse(200);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->path, $this->location);
        $this->assertTrue($this->cache->exists());
        $this->assertEquals('cached body', $this->cache->content());
        $this->assertEquals($this->headers, $this->cache->headers());
    }

    public function testProcessResponseWrongCode()
    {
        $response = $this->buildResponse(403);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->path, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseWithConfiguredHttpCode()
    {
        $response = $this->buildResponse(403);

        $middleware = $this->buildMiddleware([403]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->path, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    private function buildResponse(int $httpCode)
    {
        $this->path = '/file.txt';
        $this->headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $this->request = new ProcessingRequest(['requestPath' => $this->path]);

        return new Response([
            'body' => 'cached body', 'httpCode' => $httpCode, 'headers' => $this->headers,
            'request' => $this->request
        ]);
    }

    private function buildMiddleware(array $httpCodes = [200]): FileCacheMiddleware
    {
        return FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'httpCodes' => $httpCodes,
        ]);
    }
}
