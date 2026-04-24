<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Content\FileCache;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareProcessRequestTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $request;
    private $response;
    private $path;
    private $headers;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testProcessRequestReturnsCachedResponseWhenExists()
    {
        $this->path = '/file.txt';
        $this->request = $this->buildRequest($this->path, 'GET');
        $this->buildCache();

        $middleware = $this->buildMiddleware();
        $result = $middleware->processRequest($this->request);

        $this->assertTrue($result->hasResponse());
        $this->assertNotNull($result->response());
        $this->assertEquals('cached body', $result->response()->body());
        foreach ($this->headers as $header) {
            $this->assertContains($header, $result->response()->headers());
        }
    }

    public function testProcessRequestReturnsRequestWhenCacheDoesNotExist()
    {
        $this->path = '/file.txt';
        $this->request = $this->buildRequest($this->path, 'GET');
        $middleware = $this->buildMiddleware();
        $result = $middleware->processRequest($this->request);

        $this->assertFalse($result->hasResponse());
        $this->assertSame($this->request, $result);
    }

    public function testProcessRequestReturnsRequestWhenMethodDoesNotMatch()
    {
        $this->path = '/file.txt';
        $this->request = $this->buildRequest($this->path, 'POST');
        $this->buildCache();

        $middleware = $this->buildMiddleware([
            'matchers' => [
                [
                    'class' => \Tent\Matchers\RequestMethodMatcher::class,
                    'requestMethods' => ['GET'],
                ]
            ]
        ]);
        $result = $middleware->processRequest($this->request);

        $this->assertFalse($result->hasResponse());
        $this->assertSame($this->request, $result);
    }

    public function testProcessRequestReturnsRequestWithCustomRequestMethod()
    {
        $this->path = '/file.txt';
        $this->request = $this->buildRequest($this->path, 'POST');
        $this->buildCache();

        $middleware = $this->buildMiddleware([
            'matchers' => [
                [
                    'class' => \Tent\Matchers\RequestMethodMatcher::class,
                    'requestMethods' => ['POST'],
                ]
            ]
        ]);

        $result = $middleware->processRequest($this->request);

        $this->assertTrue($result->hasResponse());
        $this->assertSame($this->request, $result);
    }

    public function testLogsDebugWhenServingCached404(): void
    {
        $this->path = '/api/users';
        $this->request = $this->buildRequest($this->path, 'GET');

        $this->headers = ['Content-Type: text/plain'];
        $this->response = new Response([
            'body' => 'Not Found',
            'httpCode' => 404,
            'headers' => $this->headers,
            'request' => $this->request
        ]);
        $cache = new FileCache($this->request, $this->location);
        $cache->store($this->response);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('404: serving cached response — uri: /api/users', 'debug');
        Logger::setInstance($instance);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->request);
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

    private function buildCache(): void
    {
        $this->headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $this->response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => $this->headers,
            'request' => $this->request
        ]);
        $cache = new FileCache($this->request, $this->location);
        $cache->store($this->response);
    }
}
