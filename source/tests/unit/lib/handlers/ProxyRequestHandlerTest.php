<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Models\Server;
use Tent\Http\HttpClientInterface;

require_once __DIR__ . '/../../../support/tests_loader.php';

class ProxyRequestHandlerTest extends TestCase
{
    public function testHandleRequestReturnsForbiddenResponseForPathTraversal()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/assets/../secret.txt');
        $request->method('query')->willReturn('');
        $request->method('headers')->willReturn([]);

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(\Tent\Models\ForbiddenResponse::class, $response);
        $this->assertSame(403, $response->httpCode);
        $this->assertSame('Forbidden', $response->body);
    }
    public function testHandleRequestBuildsCorrectUrl()
    {
        $request = $this->createMockRequest('GET', '/api/users', '');
        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users',
            [],
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestAppendsQueryString()
    {
        $request = $this->createMockRequest('GET', '/api/users', 'page=1&limit=10');
        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users?page=1&limit=10',
            [],
            ['body' => 'response body', 'httpCode' => 200, 'headers' => []]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestForwardsHeaders()
    {
        $request = $this->createMockRequest('POST', '/api/users', '', [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123'
        ]);

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123'
        ];

        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users',
            $expectedHeaders,
            ['body' => 'created', 'httpCode' => 201, 'headers' => ['Location: /api/users/1']]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestReturnsResponseWithCorrectData()
    {
        $request = $this->createMockRequest('GET', '/api/users', '');
        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users',
            [],
            [
                'body' => '{"users": []}',
                'httpCode' => 200,
                'headers' => ['Content-Type: application/json']
            ]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertEquals('{"users": []}', $response->body);
        $this->assertEquals(200, $response->httpCode);
        $this->assertEquals(['Content-Type: application/json'], $response->headerLines);
    }

    public function testHandleRequestWithNoQueryString()
    {
        $request = $this->createMockRequest('GET', '/api/users', null);
        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users',
            [],
            ['body' => 'response', 'httpCode' => 200, 'headers' => []]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestWithEmptyHeaders()
    {
        $request = $this->createMockRequest('GET', '/api/users', '', []);
        $httpClient = $this->createMockHttpClient(
            'http://backend:8080/api/users',
            [],
            ['body' => 'response', 'httpCode' => 200, 'headers' => []]
        );

        $server = new Server('http://backend:8080');
        $handler = new ProxyRequestHandler($server, $httpClient);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    private function createMockRequest($method, $url, $query = '', $headers = [])
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn($method);
        $request->method('requestUrl')->willReturn($url);
        $request->method('query')->willReturn($query);
        $request->method('headers')->willReturn($headers);

        return $request;
    }

    private function createMockHttpClient($expectedUrl, $expectedHeaders, $returnValue)
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with($expectedUrl, $expectedHeaders)
            ->willReturn($returnValue);

        return $httpClient;
    }
}
