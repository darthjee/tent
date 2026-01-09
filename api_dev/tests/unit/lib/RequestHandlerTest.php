<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\RequestHandler;
use ApiDev\Configuration;
use ApiDev\Request;
use ApiDev\Response;
use ApiDev\MissingResponse;
use ApiDev\HealthCheckEndpoint;

require_once __DIR__ . '/../../../source/lib/models/Request.php';
require_once __DIR__ . '/../../../source/lib/models/Response.php';
require_once __DIR__ . '/../../../source/lib/models/MissingResponse.php';
require_once __DIR__ . '/../../../source/lib/Route.php';
require_once __DIR__ . '/../../../source/lib/Endpoint.php';
require_once __DIR__ . '/../../../source/lib/endpoints/HealthCheckEndpoint.php';
require_once __DIR__ . '/../../../source/lib/RouteConfiguration.php';
require_once __DIR__ . '/../../../source/lib/Configuration.php';
require_once __DIR__ . '/../../../source/lib/RequestHandler.php';

class RequestHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::reset();
    }

    protected function tearDown(): void
    {
        Configuration::reset();
    }

    public function testGetResponseReturnsMatchingRouteResponse()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('GET', '/health');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertEquals('{"status":"ok"}', $response->body);
    }

    public function testGetResponseReturnsMissingResponseWhenNoMatch()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('GET', '/unknown');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode);
    }

    public function testGetResponseReturnsFirstMatchingRoute()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        Configuration::add('POST', '/health', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('GET', '/health');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
    }

    public function testGetResponseWithDifferentHttpMethods()
    {
        Configuration::add('POST', '/api', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('POST', '/api');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertEquals(200, $response->httpCode);
    }

    public function testGetResponseWithMultipleRoutes()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        Configuration::add('GET', '/api', HealthCheckEndpoint::class);
        Configuration::add('POST', '/users', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('GET', '/api');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertEquals(200, $response->httpCode);
    }

    public function testGetResponseReturnsMissingResponseWhenNoRoutesConfigured()
    {
        $request = $this->createMockRequest('GET', '/health');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(MissingResponse::class, $response);
    }

    public function testGetResponseWithMethodMismatch()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('POST', '/health');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(MissingResponse::class, $response);
    }

    public function testGetResponseWithPathMismatch()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        
        $request = $this->createMockRequest('GET', '/health/check');
        $handler = new RequestHandler();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($handler, $request);
        
        $this->assertInstanceOf(MissingResponse::class, $response);
    }

    private function createMockRequest($method, $url)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestUrl')->willReturn($url);
        return $mock;
    }
}
