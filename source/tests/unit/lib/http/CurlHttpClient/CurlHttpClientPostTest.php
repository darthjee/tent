<?php

namespace Tent\Tests\Http;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpClient;

class CurlHttpClientPostTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testPostReturnsArrayWithCorrectKeys()
    {
        $client = new CurlHttpClient();

        $result = $client->request('POST', $this->baseUrl . '/post', [], '');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testPostReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->request('POST', $this->baseUrl . '/post', [], '');

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testPostWithJsonBody()
    {
        $client = new CurlHttpClient();

        $payload = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $headers = ['Content-Type' => 'application/json'];

        $result = $client->request('POST', $this->baseUrl . '/post', $headers, $payload);

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

        $result = $client->request('POST', $this->baseUrl . '/post', $headers, 'test data');

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

        $result = $client->request('POST', $this->baseUrl . '/post', $headers, $formData);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin parses form data and returns it
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('form', $body);
        $this->assertEquals('value1', $body['form']['field1']);
        $this->assertEquals('value2', $body['form']['field2']);
    }
}
