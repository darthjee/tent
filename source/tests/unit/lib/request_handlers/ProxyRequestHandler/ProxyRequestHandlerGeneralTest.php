<?php

namespace Tent\Tests\RequestHandlers\ProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Http\HttpClientInterface;

class ProxyRequestHandlerGeneralTest extends TestCase
{
    private ?ProcessingRequest $request = null;
    private ?HttpClientInterface $httpClient = null;
    private ?string $requestMethod = null;
    private ?string $requestPath = null;
    private ?string $requestQuery = null;
    private ?array $requestHeaders = null;

    private function initVariables($overrides = []): void
    {
        $this->requestMethod = $overrides['requestMethod'] ?? 'GET';
        $this->requestPath = $overrides['requestPath'] ?? '/api/users';
        $this->requestQuery = $overrides['requestQuery'] ?? '';
        $this->requestHeaders = $overrides['requestHeaders'] ?? [];
    }

    public function testHandleRequestBuildsCorrectUrl()
    {
        $this->initVariables();
        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'headers' => $this->requestHeaders,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery
        ]);
        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath,
            $this->requestHeaders,
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestAppendsQueryString()
    {
        $this->initVariables(['requestQuery' => 'page=1&limit=10']);
        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'requestPath' => $this->requestPath,
            'headers' => $this->requestHeaders,
            'query' => $this->requestQuery
        ]);
        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath . '?' . $this->requestQuery,
            $this->requestHeaders,
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
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
        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery,
            'headers' => $this->requestHeaders
        ]);

        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath,
            $this->requestHeaders,
            ['body' => 'created', 'httpCode' => 201, 'headers' => ['Location: /api/users/1']]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $this->initVariables();

        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery,
            'headers' => $this->requestHeaders
        ]);
        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath,
            $this->requestHeaders,
            [
                'body' => '{"users": []}',
                'httpCode' => 200,
                'headers' => ['Content-Type: application/json']
            ]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertEquals('{"users": []}', $response->body());
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->headers());
    }

    public function testHandleRequestWithNoQueryString()
    {
        $this->initVariables();

        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery,
            'headers' => $this->requestHeaders
        ]);
        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath,
            $this->requestHeaders,
            ['body' => 'response', 'httpCode' => 200, 'headers' => []]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestWithEmptyHeaders()
    {
        $this->initVariables();
        $this->request = new ProcessingRequest([
            'requestMethod' => $this->requestMethod,
            'requestPath' => $this->requestPath,
            'query' => $this->requestQuery,
            'headers' => $this->requestHeaders
        ]);
        $this->createMockHttpClient(
            $this->requestMethod,
            'http://backend:8080' . $this->requestPath,
            $this->requestHeaders,
            ['body' => 'response', 'httpCode' => 200, 'headers' => []]
        );

        $host = 'http://backend:8080';
        $handler = new ProxyRequestHandler($host, $this->httpClient);
        $response = $handler->handleRequest($this->request);

        $this->assertInstanceOf(Response::class, $response);
    }

    private function createMockHttpClient($expectedMethod, $expectedUrl, $expectedHeaders, $returnValue): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with($expectedMethod, $expectedUrl, $expectedHeaders)
            ->willReturn($returnValue);
    }
}
