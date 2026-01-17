<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestTest extends TestCase
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

    public function testRequestUrlReturnsPath()
    {
        $request = new Request([
            'requestUrl' => '/api/users'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $processingRequest->requestUrl());
    }

    public function testRequestUrlReturnsPathWithoutQueryString()
    {
        $request = new Request([
            'requestUrl' => '/api/users'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $processingRequest->requestUrl());
    }

    public function testRequestUrlReturnsRootWhenEmpty()
    {
        $request = new Request([
            'requestUrl' => '/'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/', $processingRequest->requestUrl());
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

    public function testRequestUrlWithComplexPath()
    {
        $request = new Request([
            'requestUrl' => '/api/v1/users/123/posts',
            'query' => 'filter=active'
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/v1/users/123/posts', $processingRequest->requestUrl());
        $this->assertEquals('filter=active', $processingRequest->query());
    }

    public function testReturnsNullIfNoRequestProvided()
    {
        $processingRequest = new ProcessingRequest([]);
        $this->assertNull($processingRequest->requestMethod());
        $this->assertNull($processingRequest->body());
        $this->assertNull($processingRequest->headers());
        $this->assertNull($processingRequest->requestUrl());
        $this->assertNull($processingRequest->query());
    }
}
