<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;
use Tent\Models\Response;

class ProcessingRequestGeneralTest extends TestCase
{
    public function testRequestMethodReturnsGetMethod()
    {
        $request = new Request([
            'requestMethod' => 'GET'
        ]);

        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('GET', $processingRequest->requestMethod());
    }

    public function testRequestMethodReturnsPostMethod()
    {
        $request = new Request([
            'requestMethod' => 'POST'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('POST', $processingRequest->requestMethod());
    }

    public function testRequestPathReturnsPath()
    {
        $request = new Request([
            'requestPath' => '/api/users'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $processingRequest->requestPath());
    }

    public function testRequestPathReturnsPathWithoutQueryString()
    {
        $request = new Request([
            'requestPath' => '/api/users'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $processingRequest->requestPath());
    }

    public function testRequestPathReturnsRootWhenEmpty()
    {
        $request = new Request([
            'requestPath' => '/'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/', $processingRequest->requestPath());
    }

    public function testQueryReturnsQueryString()
    {
        $request = new Request([
            'query' => 'page=1&limit=10'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('page=1&limit=10', $processingRequest->query());
    }

    public function testQueryReturnsEmptyStringWhenNoQuery()
    {
        $request = new Request([
            'query' => ''
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('', $processingRequest->query());
    }

    public function testRequestPathWithComplexPath()
    {
        $request = new Request([
            'requestPath' => '/api/v1/users/123/posts',
            'query' => 'filter=active'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/v1/users/123/posts', $processingRequest->requestPath());
        $this->assertEquals('filter=active', $processingRequest->query());
    }

    public function testOverride()
    {
        $request = new Request([
            'requestMethod' => 'PUT',
            'body' => '{"name":"test"}',
            'headers' => ['Content-Type' => 'application/json'],
            'requestPath' => '/api/v1/users/123/posts',
            'query' => 'filter=active'
        ]);
        $processingRequest = new ProcessingRequest([
            'request' => $request,
            'requestMethod' => 'GET',
            'body' => '',
            'requestPath' => '/api/v1/user',
            'headers' => ['Content-Type' => 'text/html'],
            'query' => 'filter=disabled'
        ]);
        $this->assertEquals('GET', $processingRequest->requestMethod());
        $this->assertEquals('', $processingRequest->body());
        $this->assertEquals('/api/v1/user', $processingRequest->requestPath());
        $this->assertEquals(['Content-Type' => 'text/html'], $processingRequest->headers());
        $this->assertEquals('filter=disabled', $processingRequest->query());
    }

    public function testReturnsNullIfNoRequestProvided()
    {
        $processingRequest = new ProcessingRequest([]);
        $this->assertNull($processingRequest->requestMethod());
        $this->assertNull($processingRequest->body());
        $this->assertNull($processingRequest->headers());
        $this->assertNull($processingRequest->requestPath());
        $this->assertNull($processingRequest->query());
    }

    public function testHasResponse()
    {
        $processingRequest = new ProcessingRequest([]);
        $this->assertFalse($processingRequest->hasResponse(), 'Should be false when no response is set');

        $request = new Request([]);
        $response = new Response([
            'body' => 'body', 'httpCode' => 200, 'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]);
        $processingRequest->setResponse($response);
        $this->assertTrue($processingRequest->hasResponse(), 'Should be true when a response is set');
    }
}
