<?php

namespace Tent\Tests\Http;

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpClient;

class CurlHttpClientTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testRequestReturnsArrayWithCorrectKeys()
    {
        $client = new CurlHttpClient();

        $result = $client->request($this->baseUrl . '/get', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testRequestReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->request($this->baseUrl . '/get', []);

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testRequestWithHeaders()
    {
        $client = new CurlHttpClient();

        $headers = [
            'User-Agent' => 'PHPUnit-Test',
            'Accept' => 'application/json'
        ];

        $result = $client->request($this->baseUrl . '/headers', $headers);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test', $body['headers']['User-Agent']);
    }

    public function testRequestReturnsHeadersArray()
    {
        $client = new CurlHttpClient();

        $result = $client->request($this->baseUrl . '/get', []);

        $this->assertIsArray($result['headers']);
        $this->assertNotEmpty($result['headers']);

        // Verify headers are in correct format (key: value)
        foreach ($result['headers'] as $header) {
            $this->assertStringContainsString(':', $header);
        }
    }

    public function testRequestHandles404()
    {
        $client = new CurlHttpClient();

        $result = $client->request($this->baseUrl . '/status/404', []);

        $this->assertEquals(404, $result['httpCode']);
    }

    public function testRequestWithQueryParameters()
    {
        $client = new CurlHttpClient();

        // httpbin/get?param=value should echo back the params
        $result = $client->request($this->baseUrl . '/get?test=value&foo=bar', []);

        $this->assertEquals(200, $result['httpCode']);

        $body = json_decode($result['body'], true);
        $this->assertEquals('value', $body['args']['test']);
        $this->assertEquals('bar', $body['args']['foo']);
    }
}
