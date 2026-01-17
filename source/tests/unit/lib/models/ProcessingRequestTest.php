<?php

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestTest extends TestCase
{
    public function testRequestMethodReturnsGetMethod()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('GET', $pr->requestMethod());
    }

    public function testRequestMethodReturnsPostMethod()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('POST');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('POST', $pr->requestMethod());
    }

    public function testRequestUrlReturnsPath()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/api/users');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $pr->requestUrl());
    }

    public function testRequestUrlReturnsPathWithoutQueryString()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/api/users');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/users', $pr->requestUrl());
    }

    public function testRequestUrlReturnsRootWhenEmpty()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/', $pr->requestUrl());
    }

    public function testQueryReturnsQueryString()
    {
        $request = $this->createMock(Request::class);
        $request->method('query')->willReturn('page=1&limit=10');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('page=1&limit=10', $pr->query());
    }

    public function testQueryReturnsEmptyStringWhenNoQuery()
    {
        $request = $this->createMock(Request::class);
        $request->method('query')->willReturn('');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('', $pr->query());
    }

    public function testRequestUrlWithComplexPath()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/api/v1/users/123/posts');
        $request->method('query')->willReturn('filter=active');
        $pr = new ProcessingRequest(['request' => $request]);
        $this->assertEquals('/api/v1/users/123/posts', $pr->requestUrl());
        $this->assertEquals('filter=active', $pr->query());
    }

    public function testReturnsNullIfNoRequestProvided()
    {
        $pr = new ProcessingRequest([]);
        $this->assertNull($pr->requestMethod());
        $this->assertNull($pr->body());
        $this->assertNull($pr->headers());
        $this->assertNull($pr->requestUrl());
        $this->assertNull($pr->query());
    }
}
