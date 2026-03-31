<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Route;
use ApiDev\Request;

require_once __DIR__ . '/../../../support/tests_loader.php';

class RouteTest extends TestCase
{
    public function testMatchesWithExactMethodAndPath()
    {
        $request = $this->createMockRequest('GET', '/health');
        $route = new Route('GET', '/health');

        $this->assertTrue($route->matches($request));
    }

    public function testDoesNotMatchWithDifferentMethod()
    {
        $request = $this->createMockRequest('POST', '/health');
        $route = new Route('GET', '/health');

        $this->assertFalse($route->matches($request));
    }

    public function testDoesNotMatchWithDifferentPath()
    {
        $request = $this->createMockRequest('GET', '/about');
        $route = new Route('GET', '/health');

        $this->assertFalse($route->matches($request));
    }

    public function testMatchesWithNullMethod()
    {
        $request = $this->createMockRequest('POST', '/health');
        $route = new Route(null, '/health');

        $this->assertTrue($route->matches($request));
    }

    public function testMatchesWithNullPath()
    {
        $request = $this->createMockRequest('GET', '/any/path');
        $route = new Route('GET', null);

        $this->assertTrue($route->matches($request));
    }

    public function testMatchesRootPath()
    {
        $request = $this->createMockRequest('GET', '/');
        $route = new Route('GET', '/');

        $this->assertTrue($route->matches($request));
    }

    public function testDoesNotMatchSimilarPaths()
    {
        $request = $this->createMockRequest('GET', '/health/check');
        $route = new Route('GET', '/health');

        $this->assertFalse($route->matches($request));
    }

    public function testMatchesDifferentHttpMethods()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = $this->createMockRequest($method, '/api');
            $route = new Route($method, '/api');

            $this->assertTrue($route->matches($request));
        }
    }

    public function testMatchesComplexPath()
    {
        $request = $this->createMockRequest('GET', '/api/v1/users/123');
        $route = new Route('GET', '/api/v1/users/123');

        $this->assertTrue($route->matches($request));
    }

    public function testPatternMatchesWithSingleParam()
    {
        $request = $this->createMockRequest('POST', '/persons/1/photo.json');
        $route = new Route('POST', '/persons/:id/photo.json');

        $this->assertTrue($route->matches($request));
    }

    public function testPatternMatchesWithLargeId()
    {
        $request = $this->createMockRequest('POST', '/persons/999/photo.json');
        $route = new Route('POST', '/persons/:id/photo.json');

        $this->assertTrue($route->matches($request));
    }

    public function testPatternDoesNotMatchMissingSegment()
    {
        $request = $this->createMockRequest('POST', '/persons/photo.json');
        $route = new Route('POST', '/persons/:id/photo.json');

        $this->assertFalse($route->matches($request));
    }

    public function testPatternDoesNotMatchExtraSegment()
    {
        $request = $this->createMockRequest('POST', '/persons/1/2/photo.json');
        $route = new Route('POST', '/persons/:id/photo.json');

        $this->assertFalse($route->matches($request));
    }

    public function testPatternDoesNotMatchDifferentSuffix()
    {
        $request = $this->createMockRequest('POST', '/persons/1/avatar.json');
        $route = new Route('POST', '/persons/:id/photo.json');

        $this->assertFalse($route->matches($request));
    }

    public function testExactMatchStillWorksWhenNoParamPresent()
    {
        $request = $this->createMockRequest('GET', '/persons');
        $route = new Route('GET', '/persons');

        $this->assertTrue($route->matches($request));
    }

    private function createMockRequest($method, $url)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestUrl')->willReturn($url);
        return $mock;
    }
}
