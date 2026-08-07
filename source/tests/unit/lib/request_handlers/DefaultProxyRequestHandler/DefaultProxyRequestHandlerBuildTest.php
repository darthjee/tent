<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\DefaultProxyRequestHandler;
use Tent\RequestHandlers\RequestHandler;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Middlewares\FilterQueryParamsMiddleware;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Tests\Support\Cache\DummyRequestHasher;

class DefaultProxyRequestHandlerBuildTest extends TestCase
{
    private ?string $cacheDir = null;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/default_proxy_build_test_' . uniqid();
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
    }

    public function testBuildWithCacheFalseDoesNotAddCacheMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => false,
        ]);

        $middlewares = $this->getMiddlewares($handler);

        foreach ($middlewares as $middleware) {
            $this->assertNotInstanceOf(FileCacheMiddleware::class, $middleware);
        }
    }

    public function testBuildWithCacheFalseCallsHttpClient()
    {
        $request = $this->buildRequest();
        $this->warmCache($request, 'cached body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn(['body' => 'upstream body', 'httpCode' => 200, 'headers' => []]);

        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => false,
        ]);
        $this->injectHttpClient($handler, $httpClient);

        $response = $handler->handleRequest($request);

        $this->assertSame('upstream body', $response->body());
    }

    public function testBuildWithCustomCachePathAddsCacheMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => $this->cacheDir,
        ]);

        $middlewares = $this->getMiddlewares($handler);
        $hasCacheMiddleware = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof FileCacheMiddleware) {
                $hasCacheMiddleware = true;
            }
        }

        $this->assertTrue($hasCacheMiddleware);
    }

    public function testBuildWithCustomCachePathServesFromCache()
    {
        $request = $this->buildRequest();
        $this->warmCache($request, 'cached body');

        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => $this->cacheDir,
        ]);

        $response = $handler->handleRequest($request);

        $this->assertSame('cached body', $response->body());
    }

    public function testBuildWithoutCacheKeyAddsCacheMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
        ]);

        $middlewares = $this->getMiddlewares($handler);
        $hasCacheMiddleware = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof FileCacheMiddleware) {
                $hasCacheMiddleware = true;
            }
        }

        $this->assertTrue($hasCacheMiddleware);
    }

    public function testBuildWithRequestHasherPassesThroughToFileCacheMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => $this->cacheDir,
            'request_hasher' => [
                'class' => DummyRequestHasher::class,
            ],
        ]);

        $middlewares = $this->getMiddlewares($handler);
        $fileCacheMiddleware = null;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof FileCacheMiddleware) {
                $fileCacheMiddleware = $middleware;
            }
        }

        $this->assertNotNull($fileCacheMiddleware);

        $reflection = new \ReflectionClass($fileCacheMiddleware);
        $property = $reflection->getProperty('requestHasher');
        $property->setAccessible(true);

        $this->assertInstanceOf(DummyRequestHasher::class, $property->getValue($fileCacheMiddleware));
    }

    public function testBuildWithoutRequestHasherDefaultsToQueryRequestHasherOnFileCacheMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => $this->cacheDir,
        ]);

        $middlewares = $this->getMiddlewares($handler);
        $fileCacheMiddleware = null;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof FileCacheMiddleware) {
                $fileCacheMiddleware = $middleware;
            }
        }

        $this->assertNotNull($fileCacheMiddleware);

        $reflection = new \ReflectionClass($fileCacheMiddleware);
        $property = $reflection->getProperty('requestHasher');
        $property->setAccessible(true);

        $this->assertInstanceOf(
            \Tent\Cache\QueryRequestHasher::class,
            $property->getValue($fileCacheMiddleware)
        );
    }

    public function testBuildWithoutFilterQueryParamsDoesNotAddMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => false,
        ]);

        $middlewares = $this->getMiddlewares($handler);

        foreach ($middlewares as $middleware) {
            $this->assertNotInstanceOf(FilterQueryParamsMiddleware::class, $middleware);
        }
    }

    public function testBuildWithFilterQueryParamsAddsMiddleware()
    {
        $handler = DefaultProxyRequestHandler::build([
            'host' => 'http://backend:80',
            'cache' => false,
            'filter_query_params' => [
                'params' => ['id'],
                'mode' => 'allow',
            ],
        ]);

        $middlewares = $this->getMiddlewares($handler);
        $hasFilterQueryParamsMiddleware = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof FilterQueryParamsMiddleware) {
                $hasFilterQueryParamsMiddleware = true;
            }
        }

        $this->assertTrue($hasFilterQueryParamsMiddleware);
    }

    public function testBuildWithPrependMiddlewaresRunsBeforeHandlerDefaults()
    {
        $capturedHeaders = null;

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return ['body' => 'upstream body', 'httpCode' => 200, 'headers' => []];
            });

        $handler = RequestHandler::build([
            'type' => 'default_proxy',
            'host' => 'http://backend:80',
            'cache' => false,
            'prependMiddlewares' => [
                [
                    'class' => SetHeadersMiddleware::class,
                    'headers' => ['Host' => 'prepended-host'],
                ],
            ],
        ]);
        $this->injectHttpClient($handler, $httpClient);

        $request = $this->buildRequest();
        $handler->handleRequest($request);

        $this->assertSame('backend:80', $capturedHeaders['Host']);
    }

    private function buildRequest(array $overrides = []): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestMethod' => $overrides['requestMethod'] ?? 'GET',
            'body' => $overrides['body'] ?? null,
            'headers' => $overrides['headers'] ?? [],
            'requestPath' => $overrides['requestPath'] ?? '/api/users',
            'query' => $overrides['query'] ?? '',
        ]);
    }

    private function warmCache(ProcessingRequest $request, string $body): void
    {
        $location = new FolderLocation($this->cacheDir);
        $response = new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request,
        ]);
        $cache = new FileCache($request, $location);
        $cache->store($response);
    }

    private function injectHttpClient(DefaultProxyRequestHandler $handler, HttpClientInterface $httpClient): void
    {
        $reflection = new \ReflectionClass($handler);
        $property = $reflection->getParentClass()->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($handler, $httpClient);
    }

    private function getMiddlewares(DefaultProxyRequestHandler $handler): array
    {
        $reflection = new \ReflectionClass($handler);
        $property = $reflection->getParentClass()->getParentClass()->getProperty('middlewares');
        $property->setAccessible(true);
        return $property->getValue($handler);
    }
}
