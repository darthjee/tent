<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\DefaultProxyRequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;

class DefaultProxyRequestHandlerCachedTest extends TestCase
{
    private ?string $host = null;
    private ?ProcessingRequest $request = null;
    private ?HttpClientInterface $httpClient = null;
    private ?string $requestMethod = null;
    private ?string $requestPath = null;
    private ?string $requestQuery = null;
    private ?array $requestHeaders = null;
    private ?string $requestBody = null;
    private ?string $cacheDir = null;
    private ?string $cachedBody = null;

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

        $handler = new DefaultProxyRequestHandler($this->host, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestAppendsQueryStringWithoutCache()
    {
        $this->initVariables(['requestQuery' => 'page=1&limit=10']);
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->host, $this->cacheDir, ['2xx']);
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

        $handler = new DefaultProxyRequestHandler($this->host, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertSame($this->cachedBody, $response->body());
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $this->initVariables();
        $this->buildCache();

        $handler = new DefaultProxyRequestHandler($this->host, $this->cacheDir, ['2xx']);
        $response = $handler->handleRequest($this->request);

        $this->assertSame($this->cachedBody, $response->body());
    }

    private function expectedHeadersAfterDefaultMiddlewares(): array
    {
        $headers = $this->requestHeaders ?? [];

        if (array_key_exists('Host', $headers)) {
            $headers['X-Forwarded-Host'] = $headers['Host'];
            unset($headers['Host']);
        }

        $headers['Host'] = $this->host;

        return $headers;
    }

    private function initVariables(array $overrides = []): void
    {
        $this->requestMethod = $overrides['requestMethod'] ?? 'GET';
        $this->requestPath = $overrides['requestPath'] ?? '/api/users';
        $this->requestQuery = $overrides['requestQuery'] ?? '';
        $this->requestHeaders = $overrides['requestHeaders'] ?? [];
        $this->requestBody = $overrides['requestBody'] ?? null;
        $this->host = $overrides['host'] ?? 'http://backend:8080';
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
