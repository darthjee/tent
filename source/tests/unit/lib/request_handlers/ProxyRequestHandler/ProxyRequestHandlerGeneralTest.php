<?php

namespace Tent\Tests\RequestHandlers\ProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Http\HttpClientInterface;

class ProxyRequestHandlerGeneralTest extends TestCase
{
    private ?string $host = null;
    private ?ProcessingRequest $request = null;
    private ?HttpClientInterface $httpClient = null;
    private ?string $requestMethod = null;
    private ?string $requestPath = null;
    private ?string $requestQuery = null;
    private ?array $requestHeaders = null;

    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testHandleRequestBuildsCorrectUrl()
    {
        $this->initVariables();
        $this->createMockHttpClient(
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestAppendsQueryString()
    {
        $this->initVariables(['requestQuery' => 'page=1&limit=10']);
        $this->createMockHttpClient(
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestForwardsHeaders()
    {
        $this->initVariables([
            'requestHeaders' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer token123'
            ]
        ]);

        $this->createMockHttpClient(
            ['body' => 'created', 'httpCode' => 201, 'headers' => ['Location: /api/users/1']]
        );

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $this->initVariables();
        $this->createMockHttpClient(
            ['body' => '{"users": []}', 'httpCode' => 200, 'headers' => ['Content-Type: application/json']]
        );

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertEquals('{"users": []}', $response->body());
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->headers());
    }

    public function testHandleRequestWithNoQueryString()
    {
        $this->initVariables();
        $this->createMockHttpClient(
            ['body' => 'response', 'httpCode' => 200, 'headers' => []]
        );

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testLogsDebugWhenUpstreamReturns404(): void
    {
        $this->initVariables();

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with(
                '404: upstream returned 404 — method: GET, uri: /api/users, upstream: http://backend:8080/api/users',
                'debug'
            );
        Logger::setInstance($instance);

        $this->createMockHttpClient(['body' => 'Not Found', 'httpCode' => 404, 'headers' => []]);

        $handler = new ProxyRequestHandler($this->host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertEquals(404, $response->httpCode());
    }

    private function createMockHttpClient($returnValue): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $expectedUrl = $this->host . $this->requestPath . ($this->requestQuery ? '?' . $this->requestQuery : '');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with($this->requestMethod, $expectedUrl, $this->requestHeaders)
            ->willReturn($returnValue);
    }

    private function initVariables($overrides = []): void
    {
        $this->requestMethod = $overrides['requestMethod'] ?? 'GET';
        $this->requestPath = $overrides['requestPath'] ?? '/api/users';
        $this->requestQuery = $overrides['requestQuery'] ?? '';
        $this->requestHeaders = $overrides['requestHeaders'] ?? [];
        $this->host = $overrides['host'] ?? 'http://backend:8080';

        $this->request = $this->buildProcessingRequest();
    }

    private function buildProcessingRequest(): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'headers' => $this->requestHeaders,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery
        ]);
    }
}
