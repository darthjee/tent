<?php

namespace Tent\Tests\Service;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Service\BackgroundRefresher;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\ProcessingRequest;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Tests\Support\Utils\FileSystemUtils;

class BackgroundRefresherTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $request;
    private $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/background_refresher_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRunFetchesFromUpstreamAndStoresFreshContent()
    {
        $this->request = $this->buildRequest('/users', 'GET');
        $this->cache = new FileCache($this->request, $this->location);
        $this->storeStaleCache('stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://api:80/users', [], null, [], [])
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => ['X-Test: 1']]);

        $refresher = new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient);
        $refresher->run();

        $this->assertEquals('fresh body', $this->cache->content());
        $this->assertContains('X-Test: 1', $this->cache->headers());
        $this->assertEquals(200, $this->cache->httpCode());
    }

    public function testRunUpdatesTheStoredTimestamp()
    {
        $this->request = $this->buildRequest('/users', 'GET');
        $this->cache = new FileCache($this->request, $this->location);
        $this->storeStaleCache('stale body', time() - 1000);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $refresher = new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient);
        $refresher->run();

        $this->assertEqualsWithDelta(time(), $this->cache->timestamp(), 5);
    }

    public function testRunBuildsUrlWithQueryString()
    {
        $this->request = $this->buildRequest('/users', 'GET', 'page=2');
        $this->cache = new FileCache($this->request, $this->location);
        $this->storeStaleCache('stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://api:80/users?page=2', [], null, [], [])
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        $refresher = new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient);
        $refresher->run();
    }

    public function testRunDoesNotThrowWhenUpstreamFails()
    {
        $this->request = $this->buildRequest('/users', 'GET');
        $this->cache = new FileCache($this->request, $this->location);
        $this->storeStaleCache('stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \RuntimeException('connection refused'));

        $refresher = new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient);

        $this->expectNotToPerformAssertions();
        $refresher->run();
    }

    public function testRunKeepsStaleBodyWhenUpstreamFails()
    {
        $this->request = $this->buildRequest('/users', 'GET');
        $this->cache = new FileCache($this->request, $this->location);
        $this->storeStaleCache('stale body');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \RuntimeException('connection refused'));

        $refresher = new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient);
        $refresher->run();

        $this->assertEquals('stale body', $this->cache->content());
    }

    private function storeStaleCache(string $body, ?int $timestamp = null): void
    {
        $response = new \Tent\Models\Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => [],
            'request' => $this->request
        ]);
        $this->cache->store($response);

        if ($timestamp !== null) {
            $this->overrideTimestamp($timestamp);
        }
    }

    private function overrideTimestamp(int $timestamp): void
    {
        $reflection = new \ReflectionClass($this->cache);
        $metaFilePathProp = $reflection->getProperty('metaFilePath');
        $metaFilePathProp->setAccessible(true);
        $metaFilePath = $metaFilePathProp->getValue($this->cache);

        $meta = json_decode(file_get_contents($metaFilePath), true);
        $meta['timestamp'] = $timestamp;
        file_put_contents($metaFilePath, json_encode($meta));
    }

    private function buildRequest(string $path, string $method, string $query = ''): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath' => $path,
            'requestMethod' => $method,
            'query' => $query,
            'headers' => [],
        ]);
    }
}
