<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\DefaultProxyRequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Tests\Support\Utils\FileSystemUtils;

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

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/default_proxy_handler_test_' . uniqid();
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
    }

    public function testHandleRequestBuildsCorrectUrlWithoutCache()
    {
        $this->initVariables();
        $this->createMockHttpClient(
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $handler = new DefaultProxyRequestHandler($this->host, false, ['2xx'], $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestAppendsQueryStringWithoutCache()
    {
        $this->initVariables(['requestQuery' => 'page=1&limit=10']);
        $this->createMockHttpClient(
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $handler = new DefaultProxyRequestHandler($this->host, false, ['2xx'], $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestAppliesDefaultHeaderMiddlewares()
    {
        $this->initVariables([
            'requestHeaders' => [
                'Host' => 'frontend.local:8080',
                'Authorization' => 'Bearer token123'
            ]
        ]);

        $this->createMockHttpClient(
            ['body' => 'created', 'httpCode' => 201, 'headers' => ['Location: /api/users/1']]
        );

        $handler = new DefaultProxyRequestHandler($this->host, false, ['2xx'], $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $this->initVariables();
        $this->createMockHttpClient(
            ['body' => '{"users": []}', 'httpCode' => 200, 'headers' => ['Content-Type: application/json']]
        );

        $handler = new DefaultProxyRequestHandler($this->host, false, ['2xx'], $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertEquals('{"users": []}', $response->body());
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->headers());
    }

    private function createMockHttpClient(array $returnValue): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $expectedUrl = $this->host . $this->requestPath . ($this->requestQuery ? '?' . $this->requestQuery : '');
        $expectedHeaders = $this->expectedHeadersAfterDefaultMiddlewares();

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->requestMethod,
                $expectedUrl,
                $this->callback(function (array $headers) use ($expectedHeaders) {
                    return $headers == $expectedHeaders;
                }),
                $this->requestBody
            )
            ->willReturn($returnValue);
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

        $this->request = $this->buildProcessingRequest();
    }

    private function initCache(): void
    {
        
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
}
