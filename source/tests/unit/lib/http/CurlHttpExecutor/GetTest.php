<?php

namespace Tent\Tests\Http\CurlHttpExecutor;

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpExecutor\Get;

class GetTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testRequestReturnsArrayWithCorrectKeys()
    {
        $executor = new Get([
            'url' => $this->baseUrl . '/get',
            'headers' => []
        ]);

        $result = $executor->request();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testRequestReturnsSuccessfulResponse()
    {
        $executor = new Get([
            'url' => $this->baseUrl . '/get',
            'headers' => []
        ]);

        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testRequestWithHeaders()
    {
        $headers = [
            'User-Agent' => 'PHPUnit-Test',
            'Accept' => 'application/json'
        ];

        $executor = new Get([
            'url' => $this->baseUrl . '/headers',
            'headers' => $headers
        ]);

        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test', $body['headers']['User-Agent']);
    }

    public function testRequestReturnsHeadersArray()
    {
        $executor = new Get([
            'url' => $this->baseUrl . '/get',
            'headers' => []
        ]);

        $result = $executor->request();

        $this->assertIsArray($result['headers']);
        $this->assertNotEmpty($result['headers']);

        // Verify headers are in correct format (key: value)
        foreach ($result['headers'] as $header) {
            $this->assertStringContainsString(':', $header);
        }
    }

    public function testRequestHandles404()
    {
        $executor = new Get([
            'url' => $this->baseUrl . '/status/404',
            'headers' => []
        ]);

        $result = $executor->request();

        $this->assertEquals(404, $result['httpCode']);
    }

    public function testRequestWithQueryParameters()
    {
        $executor = new Get([
            'url' => $this->baseUrl . '/get?test=value&foo=bar',
            'headers' => []
        ]);

        // httpbin/get?param=value should echo back the params
        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);

        $body = json_decode($result['body'], true);
        $this->assertEquals('value', $body['args']['test']);
        $this->assertEquals('bar', $body['args']['foo']);
    }
}
