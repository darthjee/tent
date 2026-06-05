<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\DefaultProxyRequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;

class DefaultProxyRequestHandlerCachedTest extends TestCase
{
    private ?string $host = null;
    private ?ProcessingRequest $request = null;
    private ?string $requestMethod = null;
    private ?string $requestPath = null;
    private ?string $requestQuery = null;
    private ?array $requestHeaders = null;
    private ?string $requestBody = null;
    private ?string $cacheDir = null;
    private ?string $cachedBody = null;
    private ?string $baseUrl = null;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/default_proxy_handler_test_' . uniqid();
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
    }

    public function testHandleRequestBuildsCorrectUrlWithCache()
    {
        $this->initVariables();
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->baseUrl, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestAppendsQueryStringWithoutCache()
    {
        $this->initVariables(['requestQuery' => 'page=1&limit=10']);
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->baseUrl, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestAppliesDefaultHeaderMiddlewares()
    {
        $this->initVariables([
            'requestHeaders' => [
                'Host' => 'frontend.local:8080',
                'Authorization' => 'Bearer token123'
            ]
        ]);
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->baseUrl, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $this->initVariables();
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->baseUrl, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestSkipsCacheWhenSkipCacheHeaderIsPresent()
    {
        $this->initVariables([
            'requestHeaders' => [
                'x-skip-cache' => '1'
            ]
        ]);
        $this->buildCache();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'body' => 'upstream body',
                'httpCode' => 200,
                'headers' => ['Content-Type: text/plain']
            ]);

        $handler = DefaultProxyRequestHandler::build([
            'type' => 'default_proxy',
            'host' => $this->baseUrl,
            'cache' => $this->cacheDir,
            'cacheCodes' => ['2xx'],
            'skip_cache_header' => 'X-SKIP-CACHE'
        ]);

        $reflection = new \ReflectionClass($handler);
        $property = $reflection->getParentClass()->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($handler, $httpClient);

        $response = $handler->handleRequest($this->request);

        $this->assertSame('upstream body', $response->body());
    }

    private function initVariables(array $overrides = []): void
    {
        $this->requestMethod = $overrides['requestMethod'] ?? 'GET';
        $this->requestPath = $overrides['requestPath'] ?? '/api/users';
        $this->requestQuery = $overrides['requestQuery'] ?? '';
        $this->requestHeaders = $overrides['requestHeaders'] ?? [];
        $this->requestBody = $overrides['requestBody'] ?? null;
        $this->host = $overrides['host'] ?? 'backend:8080';
        $this->baseUrl = $overrides['baseUrl'] ?? 'http://' . $this->host;
        $this->cachedBody = $overrides['cachedBody'] ?? 'cached body';

        $this->request = $this->buildProcessingRequest();
    }

    private function buildProcessingRequest(): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'body' => $this->requestBody,
            'headers' => $this->requestHeaders,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery
        ]);
    }

    private function buildCache(): void
    {
        $responseHeaders = ['Content-Type: text/plain', 'Content-Length: 11'];
        $location = new FolderLocation($this->cacheDir);
        $response = new Response([
            'body' => $this->cachedBody,
            'httpCode' => 200,
            'headers' => $responseHeaders,
            'request' => $this->request
        ]);
        $cache = new FileCache($this->request, $location);
        $cache->store($response);
    }
}
