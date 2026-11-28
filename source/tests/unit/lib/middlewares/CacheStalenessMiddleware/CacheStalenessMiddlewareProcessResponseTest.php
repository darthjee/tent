<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\AllowedHeaderFilter;
use Tent\Content\CacheHit;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\HeaderFilter;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Tests\Support\Cache\DummyRequestHasher;

class CacheStalenessMiddlewareProcessResponseTest extends TestCase
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

    public function testNoRefreshWithoutCacheHitMarker()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $response = $this->buildResponse($request, 'other body');
        $request->setResponse($response);

        $result = $this->buildNeverRefreshingMiddleware()->processResponse($response);

        $this->assertSame($response, $result);
    }

    public function testNoRefreshWhenRequestIsNotAProcessingRequest()
    {
        $this->storeCache($this->buildRequest('/users', 'GET'), 'stale body', time() - 1000);
        $response = new Response([
            'body' => 'stale body',
            'request' => new Request(['requestPath' => '/users', 'requestMethod' => 'GET']),
        ]);

        $result = $this->buildNeverRefreshingMiddleware()->processResponse($response);

        $this->assertSame($response, $result);
    }

    public function testNoRefreshWhenResponseHasNoRequest()
    {
        $response = new Response(['body' => 'some body']);

        $result = $this->buildNeverRefreshingMiddleware()->processResponse($response);

        $this->assertSame($response, $result);
    }

    public function testProcessRequestIsAPassThrough()
    {
        $request = $this->buildStaleRequest();
        $response = $request->response();

        $result = $this->buildNeverRefreshingMiddleware()->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertSame($response, $result->response());
    }

    public function testFreshCacheDoesNotTriggerRefresh()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'cached body', time());
        $this->serveFromCache($request, 'cached body');

        $result = $this->buildNeverRefreshingMiddleware()->processResponse($request->response());

        $this->assertSame('cached body', $result->body());
    }

    public function testStaleCacheServesUnmodifiedResponse()
    {
        $request = $this->buildStaleRequest();
        $response = $request->response();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 201, 'headers' => ['X-Fresh: 1']]);

        $result = $this->buildMiddleware($httpClient, 300)->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertSame($response, $request->response());
        $this->assertSame('stale body', $result->body());
        $this->assertSame(200, $result->httpCode());
        $this->assertSame([], $result->headers());
    }

    public function testStaleCacheTriggersRefreshExactlyOnce()
    {
        $request = $this->buildStaleRequest();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://api:80/users', [], null, [], [])
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $this->buildMiddleware($httpClient, 300)->processResponse($request->response());
    }

    public function testStaleCacheRefreshRepopulatesCacheEntry()
    {
        $request = $this->buildStaleRequest();

        $this->buildMiddleware($this->clientReturning(200, 'fresh body'), 300)
            ->processResponse($request->response());

        $cache = new FileCache($request, $this->location);
        $this->assertEquals('fresh body', $cache->content());
    }

    public function testRefreshUsesTheHeaderFilterOfTheCacheHit()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->serveFromCache($request, 'stale body', new AllowedHeaderFilter(['Set-Cookie']));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn([
                'body' => 'fresh body',
                'httpCode' => 200,
                'headers' => ['X-Test: 1', 'Set-Cookie: session=abc'],
            ]);

        $this->buildMiddleware($httpClient, 300)->processResponse($request->response());

        $headers = (new FileCache($request, $this->location))->headers();
        $this->assertContains('Set-Cookie: session=abc', $headers);
        $this->assertNotContains('X-Test: 1', $headers);
    }

    public function testRefreshIgnoresDeprecatedHeaderFilterOptions()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'additional_excluded_headers' => ['X-Secret'],
        ])->processRequest($request);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn([
                'body' => 'fresh body',
                'httpCode' => 200,
                'headers' => ['X-Test: 1', 'X-Secret: 1', 'Set-Cookie: session=abc'],
            ]);

        $middleware = CacheStalenessMiddleware::build([
            'location' => $this->cacheDir,
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
            'excluded_headers' => [],
        ]);
        $this->inject($middleware, 'httpClient', $httpClient);
        $this->inject($middleware, 'scheduler', function (callable $task) {
            $task();
        });

        $middleware->processResponse($request->response());

        $headers = (new FileCache($request, $this->location))->headers();
        $this->assertContains('X-Test: 1', $headers);
        $this->assertNotContains('X-Secret: 1', $headers);
        $this->assertNotContains('Set-Cookie: session=abc', $headers);
    }

    public function testChecksAndRefreshesTheEntryOfTheCacheHit()
    {
        $servedLocation = new FolderLocation($this->cacheDir . '/served');
        $request = $this->buildRequest('/users', 'GET');
        $cache = new FileCache($request, $servedLocation);
        $cache->store($this->buildResponse($request, 'stale body'));
        $this->overrideTimestamp($cache, time() - 1000);
        $request->setCacheHit(new CacheHit($cache, new ExcludedHeaderFilter([])));

        $this->buildMiddleware($this->clientReturning(200, 'fresh body'), 300)
            ->processResponse($this->buildResponse($request, 'stale body'));

        $this->assertSame('fresh body', (new FileCache($request, $servedLocation))->content());
        $this->assertFalse((new FileCache($request, $this->location))->exists());
    }

    public function testNoRefreshTriggeredWhenCacheHasNoTimestamp()
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCacheWithoutTimestamp($request, 'cached body');
        $this->serveFromCache($request, 'cached body');

        $this->buildNeverRefreshingMiddleware()->processResponse($request->response());
    }

    public function testDebounceSkipsSecondRefreshWhileFirstInFlight()
    {
        $request = $this->buildStaleRequest();

        $cache = new FileCache($request, $this->location);
        $sentinel = $cache->metaFilePath() . '.refreshing';
        file_put_contents($sentinel, (string) time());

        $this->buildNeverRefreshingMiddleware()->processResponse($request->response());

        unlink($sentinel);
    }

    public function testSentinelIsRemovedAfterRefreshCompletes()
    {
        $request = $this->buildStaleRequest();

        $this->buildMiddleware($this->clientReturning(200, 'fresh body'), 300)
            ->processResponse($request->response());

        $cache = new FileCache($request, $this->location);
        $sentinel = $cache->metaFilePath() . '.refreshing';
        $this->assertFileDoesNotExist($sentinel);
    }

    public function testLogsStalenessDetection()
    {
        $request = $this->buildStaleRequest();

        $loggedMessages = [];
        $instance = $this->createMock(LoggerInstance::class);
        $instance->method('log')
            ->willReturnCallback(function ($message, $level) use (&$loggedMessages) {
                $loggedMessages[] = $message;
            });
        Logger::setInstance($instance);

        $this->buildMiddleware($this->clientReturning(200, 'fresh body'), 300)
            ->processResponse($request->response());

        $staleMessages = array_filter($loggedMessages, function ($message) {
            return str_contains($message, '[stale]');
        });
        $this->assertNotEmpty($staleMessages);
    }

    /**
     * `FileCacheMiddleware` configured with a non-default hasher memoizes the resulting
     * hash on the request. `CacheStalenessMiddleware`, configured with no hasher at all,
     * must check and refresh the very entry which was served.
     */
    public function testReusesMemoizedHashFromFileCacheMiddlewareConfiguredWithCustomHasher()
    {
        $request = $this->buildRequest('/users', 'GET');

        $fileCache = new FileCache($request, $this->location, new DummyRequestHasher());
        $fileCache->store($this->buildResponse($request, 'stale body'));
        $this->overrideTimestamp($fileCache, time() - 1000);

        $fileCacheMiddleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'request_hasher' => [
                'class' => DummyRequestHasher::class,
            ],
        ]);
        $request = $fileCacheMiddleware->processRequest($request);

        $this->assertTrue($request->hasResponse());
        $this->assertEquals('dummy-hash', $request->cacheHash());

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $result = $this->buildMiddleware($httpClient, 300)->processResponse($request->response());

        $this->assertSame('stale body', $result->body());
        $this->assertSame(
            'fresh body',
            (new FileCache($request, $this->location, new DummyRequestHasher()))->content()
        );
    }

    public function testEvictsEntryWhenRefreshMatchesEvictOn()
    {
        $request = $this->buildStaleRequest();
        $middleware = $this->buildEvictingMiddleware($this->clientReturning(404), ['4xx']);
        $middleware->processResponse($request->response());

        $this->assertFalse((new FileCache($request, $this->location))->exists());
    }

    public function testEvictsEntryOnWildcardFamily()
    {
        $request = $this->buildStaleRequest();
        $middleware = $this->buildEvictingMiddleware($this->clientReturning(410), ['4xx']);
        $middleware->processResponse($request->response());

        $this->assertFalse((new FileCache($request, $this->location))->exists());
    }

    public function testKeepsEntryWhenCodeDoesNotMatchEvictOn()
    {
        $request = $this->buildStaleRequest();
        $middleware = $this->buildEvictingMiddleware($this->clientReturning(500), ['4xx']);
        $middleware->processResponse($request->response());

        $this->assertEquals('stale body', (new FileCache($request, $this->location))->content());
    }

    public function testKeepsEntryByDefaultOn4xx()
    {
        $request = $this->buildStaleRequest();
        $middleware = $this->buildMiddleware($this->clientReturning(404), 300);
        $middleware->processResponse($request->response());

        $this->assertEquals('stale body', (new FileCache($request, $this->location))->content());
    }

    public function testDoesNotEvictWhenRefreshThrows()
    {
        $request = $this->buildStaleRequest();
        $response = $request->response();
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('down'));

        $middleware = $this->buildEvictingMiddleware($httpClient, ['4xx', '5xx']);
        $result = $middleware->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertEquals('stale body', (new FileCache($request, $this->location))->content());
    }

    public function testSentinelIsRemovedAfterEviction()
    {
        $request = $this->buildStaleRequest();
        $middleware = $this->buildEvictingMiddleware($this->clientReturning(404), ['4xx']);
        $middleware->processResponse($request->response());

        $cache = new FileCache($request, $this->location);
        $this->assertFileDoesNotExist($cache->metaFilePath() . '.refreshing');
    }

    public function testEvictionLogsWarningWithCode()
    {
        $request = $this->buildStaleRequest();
        $logger = $this->createMock(LoggerInstance::class);
        $messages = [];
        $logger->method('log')->willReturnCallback(function ($message, $level) use (&$messages) {
            $messages[] = $message;
        });
        Logger::setInstance($logger);

        $middleware = $this->buildEvictingMiddleware($this->clientReturning(404), ['4xx']);
        $middleware->processResponse($request->response());

        $evictions = array_filter($messages, fn($m) => str_contains($m, '[404]') && str_contains($m, 'evict'));
        $this->assertNotEmpty($evictions);
    }

    public function testNoRefreshScheduledWhenCacheSkippedFlagIsSet()
    {
        $request = $this->buildStaleRequest();
        $request->skipCache();

        $scheduled = 0;
        $middleware = new CacheStalenessMiddleware(
            $this->location,
            300,
            'http://api:80',
            $this->createMock(HttpClientInterface::class),
            function (callable $task) use (&$scheduled) {
                $scheduled++;
                $task();
            }
        );
        $middleware->processResponse($request->response());

        $this->assertSame(0, $scheduled);
        $this->assertEmpty(glob($this->cacheDir . '/*.refreshing'));
        $this->assertEmpty(glob($this->cacheDir . '/**/*.refreshing'));
    }

    public function testNoRefreshNorEvictionWhenCacheSkippedFlagIsSetWithEvictOn()
    {
        $request = $this->buildStaleRequest();
        $request->skipCache();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $this->buildEvictingMiddleware($httpClient, ['4xx'])->processResponse($request->response());

        $this->assertTrue((new FileCache($request, $this->location))->exists());
    }

    private function buildStaleRequest(): ProcessingRequest
    {
        $request = $this->buildRequest('/users', 'GET');
        $this->storeCache($request, 'stale body', time() - 1000);
        $this->serveFromCache($request, 'stale body');
        return $request;
    }

    private function clientReturning(int $httpCode, string $body = 'error body'): HttpClientInterface
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => $body, 'httpCode' => $httpCode, 'headers' => []]);
        return $httpClient;
    }

    private function buildNeverRefreshingMiddleware(): CacheStalenessMiddleware
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        return $this->buildMiddleware($httpClient, 300);
    }

    private function buildEvictingMiddleware(HttpClientInterface $httpClient, array $evictOn): CacheStalenessMiddleware
    {
        return new CacheStalenessMiddleware(
            $this->location,
            300,
            'http://api:80',
            $httpClient,
            function (callable $task) {
                $task();
            },
            evictOn: $evictOn
        );
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

    private function inject(CacheStalenessMiddleware $middleware, string $name, mixed $value): void
    {
        $property = (new \ReflectionClass($middleware))->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($middleware, $value);
    }

    private function storeCache(ProcessingRequest $request, string $body, int $timestamp): void
    {
        $cache = new FileCache($request, $this->location);
        $cache->store($this->buildResponse($request, $body));
        $this->overrideTimestamp($cache, $timestamp);
    }

    private function storeCacheWithoutTimestamp(ProcessingRequest $request, string $body): void
    {
        $cache = new FileCache($request, $this->location);
        $cache->store($this->buildResponse($request, $body));

        $meta = json_decode(file_get_contents($cache->metaFilePath()), true);
        unset($meta['timestamp']);
        file_put_contents($cache->metaFilePath(), json_encode($meta));
    }

    private function overrideTimestamp(FileCache $cache, int $timestamp): void
    {
        $meta = json_decode(file_get_contents($cache->metaFilePath()), true);
        $meta['timestamp'] = $timestamp;
        file_put_contents($cache->metaFilePath(), json_encode($meta));
    }

    private function serveFromCache(
        ProcessingRequest $request,
        string $body,
        ?HeaderFilter $headerFilter = null
    ): void {
        $request->setResponse($this->buildResponse($request, $body));
        $request->setCacheHit(new CacheHit(
            new FileCache($request, $this->location),
            $headerFilter ?? new ExcludedHeaderFilter(ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS)
        ));
    }

    private function buildResponse(ProcessingRequest $request, string $body): Response
    {
        return new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => [],
            'request' => $request,
        ]);
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
