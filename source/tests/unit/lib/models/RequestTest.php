<?php

namespace Tent\Tests\Models;

use PHPUnit\Framework\TestCase;
use Tent\Models\Request;

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
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $request = new Request();

        $this->assertEquals('PUT', $request->requestMethod());
    }

    public function testRequestMethodReturnsGetMethodWithSetup()
    {
        $request = new Request(['requestMethod' => 'POST']);

        $this->assertEquals('POST', $request->requestMethod());
    }

    public function testRequestMethodReturnsPostMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();

        $this->assertEquals('POST', $request->requestMethod());
    }

    public function testRequestPathReturnsPathWithSetup()
    {
        $request = new Request(['requestPath' => '/api/users/1']);

        $this->assertEquals('/api/users/1', $request->requestPath());
    }

    public function testRequestPathReturnsPath()
    {
        $_SERVER['REQUEST_URI'] = '/api/users';

        $request = new Request();

        $this->assertEquals('/api/users', $request->requestPath());
    }

    public function testRequestPathReturnsPathWithoutQueryString()
    {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1&limit=10';

        $request = new Request();

        $this->assertEquals('/api/users', $request->requestPath());
    }

    public function testRequestPathReturnsPathWitUriSetup()
    {
        $request = new Request(['requestUri' => '/api/users/all?page=1&limit=10']);

        $this->assertEquals('/api/users/all', $request->requestPath());
    }

    public function testRequestPathReturnsRootWhenEmpty()
    {
        $_SERVER['REQUEST_URI'] = '/';

        $request = new Request();

        $this->assertEquals('/', $request->requestPath());
    }

    public function testQueryReturnsQueryString()
    {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1&limit=10';

        $request = new Request();

        $this->assertEquals('page=1&limit=10', $request->query());
    }

    public function testQueryReturnsQueryStringWithSetup()
    {
        $request = new Request(['query' => 'page=1&limit=20']);

        $this->assertEquals('page=1&limit=20', $request->query());
    }

    public function testQueryReturnsQueryStringWithUriSetup()
    {
        $request = new Request(['requestUri' => '/api/users?page=1&limit=100']);

        $this->assertEquals('page=1&limit=100', $request->query());
    }

    public function testQueryReturnsEmptyStringWhenNoQuery()
    {
        $_SERVER['REQUEST_URI'] = '/api/users';

        $request = new Request();

        $this->assertEquals('', $request->query());
    }

    public function testRequestPathWithComplexPath()
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/users/123/posts?filter=active';

        $request = new Request();

        $this->assertEquals('/api/v1/users/123/posts', $request->requestPath());
        $this->assertEquals('filter=active', $request->query());
    }
}
