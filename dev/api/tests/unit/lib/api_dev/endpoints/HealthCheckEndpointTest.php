<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\HealthCheckEndpoint;
use ApiDev\Request;
use ApiDev\Response;

require_once __DIR__ . '/../../../../../source/lib/api_dev/models/Request.php';

class HealthCheckEndpointTest extends TestCase
{
    public function testHandleReturnsResponse()
    {
        $request = new Request();
        $endpoint = new HealthCheckEndpoint($request);

        $response = $endpoint->handle();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleReturns200StatusCode()
    {
        $request = new Request();
        $endpoint = new HealthCheckEndpoint($request);

        $response = $endpoint->handle();

        $this->assertEquals(200, $response->httpCode);
    }

    public function testHandleReturnsJsonBody()
    {
        $request = new Request();
        $endpoint = new HealthCheckEndpoint($request);

        $response = $endpoint->handle();

        $this->assertEquals('{"status":"ok"}', $response->body);
    }

    public function testHandleReturnsJsonContentType()
    {
        $request = new Request();
        $endpoint = new HealthCheckEndpoint($request);

        $response = $endpoint->handle();

        $this->assertContains('Content-Type: application/json', $response->headerLines);
    }

    public function testBodyDecodesAsValidJson()
    {
        $request = new Request();
        $endpoint = new HealthCheckEndpoint($request);

        $response = $endpoint->handle();
        $data = json_decode($response->body, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('ok', $data['status']);
    }
}
