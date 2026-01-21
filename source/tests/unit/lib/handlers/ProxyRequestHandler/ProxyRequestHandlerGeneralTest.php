<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Models\Server;
use Tent\Http\HttpClientInterface;
use Tent\Models\ForbiddenResponse;

class ProxyRequestHandlerGeneralTest extends TestCase
{
    public function testHandleRequestBuildsCorrectUrl()
    {
        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'headers' => [],
            'requestPath' => '/api/users',
            'query' => ''
        ]);
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
        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/api/users',
            'headers' => [],
            'query' => 'page=1&limit=10'
        ]);
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
        $request = new ProcessingRequest([
            'requestMethod' => 'POST',
            'requestPath' => '/api/users',
            'query' => '',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer token123'
            ]
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
        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/api/users',
            'query' => '',
            'headers' => []
        ]);
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

        $this->assertEquals('{"users": []}', $response->body());
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->headerLines());
    }

    public function testHandleRequestWithNoQueryString()
    {
        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/api/users',
            'query' => '',
            'headers' => []
        ]);
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
        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/api/users',
            'query' => '',
            'headers' => []
        ]);
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
