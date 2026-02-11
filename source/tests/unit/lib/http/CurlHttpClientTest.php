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

        $result = $client->get($this->baseUrl . '/get', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testRequestReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->get($this->baseUrl . '/get', []);

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

        $result = $client->get($this->baseUrl . '/headers', $headers);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test', $body['headers']['User-Agent']);
    }

    public function testRequestReturnsHeadersArray()
    {
        $client = new CurlHttpClient();

        $result = $client->get($this->baseUrl . '/get', []);

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

        $result = $client->get($this->baseUrl . '/status/404', []);

        $this->assertEquals(404, $result['httpCode']);
    }

    public function testRequestWithQueryParameters()
    {
        $client = new CurlHttpClient();

        // httpbin/get?param=value should echo back the params
        $result = $client->get($this->baseUrl . '/get?test=value&foo=bar', []);

        $this->assertEquals(200, $result['httpCode']);

        $body = json_decode($result['body'], true);
        $this->assertEquals('value', $body['args']['test']);
        $this->assertEquals('bar', $body['args']['foo']);
    }

    public function testPostReturnsArrayWithCorrectKeys()
    {
        $client = new CurlHttpClient();

        $result = $client->post($this->baseUrl . '/post', [], '');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testPostReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->post($this->baseUrl . '/post', [], '');

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testPostWithJsonBody()
    {
        $client = new CurlHttpClient();

        $payload = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $headers = ['Content-Type' => 'application/json'];

        $result = $client->post($this->baseUrl . '/post', $headers, $payload);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes back the data sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($payload, $body['data']);
    }

    public function testPostWithCustomHeaders()
    {
        $client = new CurlHttpClient();

        $headers = [
            'User-Agent' => 'PHPUnit-Test-Post',
            'X-Custom-Header' => 'CustomValue'
        ];

        $result = $client->post($this->baseUrl . '/post', $headers, 'test data');

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test-Post', $body['headers']['User-Agent']);
        $this->assertEquals('CustomValue', $body['headers']['X-Custom-Header']);
    }

    public function testPostWithFormData()
    {
        $client = new CurlHttpClient();

        $formData = 'field1=value1&field2=value2';
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        $result = $client->post($this->baseUrl . '/post', $headers, $formData);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin parses form data and returns it
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('form', $body);
        $this->assertEquals('value1', $body['form']['field1']);
        $this->assertEquals('value2', $body['form']['field2']);
    }
}
