<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheStalenessMiddlewareProcessRequestTest extends TestCase
{
    private $cacheDir;
    private $location;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_staleness_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testNoOpWhenRequestHasNoResponse()
    {
        $request = $this->buildRequest('/users', 'GET');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $middleware = $this->buildMiddleware($httpClient, 300);
        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertFalse($result->hasResponse());
    }

    public function testFreshCacheDoesNotTriggerRefresh()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'cached body', time());
        $this->setResponseOnRequest($request, 'cached body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $middleware = $this->buildMiddleware($httpClient, 300);
        $result = $middleware->processRequest($request);

        $this->assertSame('cached body', $result->response()->body());
    }

    public function testStaleCacheServesUnmodifiedResponse()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $middleware = $this->buildMiddleware($httpClient, 300);
        $result = $middleware->processRequest($request);

        $this->assertSame('stale body', $result->response()->body());
    }

    public function testStaleCacheTriggersRefreshExactlyOnce()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://api:80/users', [], null, [], [])
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);
    }

    public function testStaleCacheRefreshRepopulatesCacheEntry()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);

        $cache = new FileCache($request, $this->location);
        $this->assertEquals('fresh body', $cache->content());
    }

    public function testNoRefreshTriggeredWhenCacheHasNoTimestamp()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCacheWithoutTimestamp($request, 'cached body');
        $this->setResponseOnRequest($request, 'cached body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);
    }

    public function testDebounceSkipsSecondRefreshWhileFirstInFlight()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $cache = new FileCache($request, $this->location);
        $sentinel = $cache->metaFilePath() . '.refreshing';
        file_put_contents($sentinel, (string) time());

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);

        unlink($sentinel);
    }

    public function testSentinelIsRemovedAfterRefreshCompletes()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);

        $cache = new FileCache($request, $this->location);
        $sentinel = $cache->metaFilePath() . '.refreshing';
        $this->assertFileDoesNotExist($sentinel);
    }

    public function testLogsStalenessDetection()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->setResponseOnRequest($request, 'stale body');

        $loggedMessages = [];
        $instance = $this->createMock(LoggerInstance::class);
        $instance->method('log')
            ->willReturnCallback(function ($message, $level) use (&$loggedMessages) {
                $loggedMessages[] = $message;
            });
        Logger::setInstance($instance);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $middleware = $this->buildMiddleware($httpClient, 300);
        $middleware->processRequest($request);

        $staleMessages = array_filter($loggedMessages, function ($message) {
            return str_contains($message, '[stale]');
        });
        $this->assertNotEmpty($staleMessages);
    }

    private function buildMiddleware(HttpClientInterface $httpClient, int $maxAgeSeconds): CacheStalenessMiddleware
    {
        return new CacheStalenessMiddleware(
            $this->location,
            $maxAgeSeconds,
            'http://api:80',
            $httpClient,
            function (callable $task) {
                $task();
            }
        );
    }

    private function storeCache(ProcessingRequest $request, string $body, int $timestamp): void
    {
        $cache = new FileCache($request, $this->location);
        $response = new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => [],
            'request' => $request,
        ]);
        $cache->store($response);
        $this->overrideTimestamp($cache, $timestamp);
    }

    private function storeCacheWithoutTimestamp(ProcessingRequest $request, string $body): void
    {
        $cache = new FileCache($request, $this->location);
        $response = new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => [],
            'request' => $request,
        ]);
        $cache->store($response);

        $reflection = new \ReflectionClass($cache);
        $metaFilePathProp = $reflection->getProperty('metaFilePath');
        $metaFilePathProp->setAccessible(true);
        $metaFilePath = $metaFilePathProp->getValue($cache);

        $meta = json_decode(file_get_contents($metaFilePath), true);
        unset($meta['timestamp']);
        file_put_contents($metaFilePath, json_encode($meta));
    }

    private function overrideTimestamp(FileCache $cache, int $timestamp): void
    {
        $reflection = new \ReflectionClass($cache);
        $metaFilePathProp = $reflection->getProperty('metaFilePath');
        $metaFilePathProp->setAccessible(true);
        $metaFilePath = $metaFilePathProp->getValue($cache);

        $meta = json_decode(file_get_contents($metaFilePath), true);
        $meta['timestamp'] = $timestamp;
        file_put_contents($metaFilePath, json_encode($meta));
    }

    private function setResponseOnRequest(ProcessingRequest $request, string $body): void
    {
        $request->setResponse(new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => [],
            'request' => $request,
        ]));
    }

    private function buildRequest(string $path, string $method): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath' => $path,
            'requestMethod' => $method,
            'headers' => [],
            'query' => '',
        ]);
    }
}
