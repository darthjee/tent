<?php

namespace Tent\Tests\Middlewares;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Models\FileCache;

class FileCacheMiddlewareProcessRequestTest extends TestCase
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

    public function testProcessRequestReturnsCachedResponseWhenExists()
    {
        $path = '/file.txt';
        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $request = $this->buildRequest($path, 'GET');
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => $headers,
            'request' => $request
        ]);
        $cache = new FileCache($path, $this->location);
        $cache->store($response);

        $middleware = $this->buildMiddleware();
        $result = $middleware->processRequest($request);

        $this->assertTrue($result->hasResponse());
        $this->assertNotNull($result->response());
        $this->assertEquals('cached body', $result->response()->body());
        $this->assertEquals($headers, $result->response()->headerLines());
    }

    public function testProcessRequestReturnsRequestWhenCacheDoesNotExist()
    {
        $path = '/file.txt';
        $request = $this->buildRequest($path, 'GET');
        $middleware = $this->buildMiddleware();
        $result = $middleware->processRequest($request);

        $this->assertFalse($result->hasResponse());
        $this->assertSame($request, $result);
    }

    public function testProcessRequestReturnsRequestWhenMethodDoesNotMatch()
    {
        $path = '/file.txt';
        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $request = $this->buildRequest($path, 'POST');
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => $headers,
            'request' => $request
        ]);
        $cache = new FileCache($path, $this->location);
        $cache->store($response);

        $middleware = $this->buildMiddleware();
        $result = $middleware->processRequest($request);

        $this->assertFalse($result->hasResponse());
        $this->assertSame($request, $result);
    }

    public function testProcessRequestReturnsRequestWithCustomRequestMethod()
    {
        $path = '/file.txt';
        $headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $request = $this->buildRequest($path, 'POST');
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => $headers,
            'request' => $request
        ]);
        $cache = new FileCache($path, $this->location);
        $cache->store($response);

        $middleware = $this->buildMiddleware([
            'requestMethods' => ['POST']
        ]);
        
        $result = $middleware->processRequest($request);

        $this->assertTrue($result->hasResponse());
        $this->assertSame($request, $result);
    }

    private function buildRequest(string $path, string $method): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath' => $path,
            'requestMethod' => $method
        ]);
    }

    private function buildMiddleware(array $attributes = []): FileCacheMiddleware
    {
        $attributes['location'] = $this->cacheDir;
        return FileCacheMiddleware::build($attributes);
    }
}
