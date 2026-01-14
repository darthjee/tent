<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\RouteConfiguration;
use ApiDev\Request;
use ApiDev\Response;
use ApiDev\HealthCheckEndpoint;

class RouteConfigurationTest extends TestCase
{
    public function testMatchReturnsTrueWhenRouteMatches()
    {
        $request = $this->createMockRequest('GET', '/health');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        $this->assertTrue($config->match($request));
    }

    public function testMatchReturnsFalseWhenRouteDoesNotMatch()
    {
        $request = $this->createMockRequest('POST', '/health');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        $this->assertFalse($config->match($request));
    }

    public function testMatchReturnsFalseWhenPathDoesNotMatch()
    {
        $request = $this->createMockRequest('GET', '/about');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        $this->assertFalse($config->match($request));
    }

    public function testHandleReturnsResponse()
    {
        $request = $this->createMockRequest('GET', '/health');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        $response = $config->handle($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleInstantiatesEndpointAndCallsHandle()
    {
        $request = $this->createMockRequest('GET', '/health');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        $response = $config->handle($request);

        $this->assertEquals(200, $response->httpCode);
        $this->assertEquals('{"status":"ok"}', $response->body);
    }

    public function testMatchAndHandleWorkTogether()
    {
        $request = $this->createMockRequest('GET', '/health');
        $config = new RouteConfiguration('GET', '/health', HealthCheckEndpoint::class);

        if ($config->match($request)) {
            $response = $config->handle($request);
            $this->assertInstanceOf(Response::class, $response);
        } else {
            $this->fail('Route should have matched');
        }
    }

    public function testConfigurationWithDifferentHttpMethods()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];

        foreach ($methods as $method) {
            $request = $this->createMockRequest($method, '/health');
            $config = new RouteConfiguration($method, '/health', HealthCheckEndpoint::class);

            $this->assertTrue($config->match($request));
            $this->assertInstanceOf(Response::class, $config->handle($request));
        }
    }

    public function testConfigurationWithDifferentPaths()
    {
        $paths = ['/health', '/api/health', '/v1/health'];

        foreach ($paths as $path) {
            $request = $this->createMockRequest('GET', $path);
            $config = new RouteConfiguration('GET', $path, HealthCheckEndpoint::class);

            $this->assertTrue($config->match($request));
        }
    }

    private function createMockRequest($method, $url)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestUrl')->willReturn($url);
        return $mock;
    }
}
