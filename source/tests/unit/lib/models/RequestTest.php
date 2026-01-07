<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Request;

require_once __DIR__ . '/../../../../source/lib/models/Request.php';

class RequestTest extends TestCase
{
    private $originalServer;

    protected function setUp(): void
    {
        // Save original values
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        // Restore original values
        $_SERVER = $this->originalServer;
    }

    public function testRequestMethodReturnsGetMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request();

        $this->assertEquals('GET', $request->requestMethod());
    }

    public function testRequestMethodReturnsPostMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();

        $this->assertEquals('POST', $request->requestMethod());
    }

    public function testRequestUrlReturnsPath()
    {
        $_SERVER['REQUEST_URI'] = '/api/users';

        $request = new Request();

        $this->assertEquals('/api/users', $request->requestUrl());
    }

    public function testRequestUrlReturnsPathWithoutQueryString()
    {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1&limit=10';

        $request = new Request();

        $this->assertEquals('/api/users', $request->requestUrl());
    }

    public function testRequestUrlReturnsRootWhenEmpty()
    {
        $_SERVER['REQUEST_URI'] = '/';

        $request = new Request();

        $this->assertEquals('/', $request->requestUrl());
    }

    public function testQueryReturnsQueryString()
    {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1&limit=10';

        $request = new Request();

        $this->assertEquals('page=1&limit=10', $request->query());
    }

    public function testQueryReturnsEmptyStringWhenNoQuery()
    {
        $_SERVER['REQUEST_URI'] = '/api/users';

        $request = new Request();

        $this->assertEquals('', $request->query());
    }

    public function testRequestUrlWithComplexPath()
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/users/123/posts?filter=active';

        $request = new Request();

        $this->assertEquals('/api/v1/users/123/posts', $request->requestUrl());
        $this->assertEquals('filter=active', $request->query());
    }
}
