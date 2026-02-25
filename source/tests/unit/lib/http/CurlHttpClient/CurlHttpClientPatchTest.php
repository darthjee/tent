<?php

namespace Tent\Tests\Http;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpClient;

class CurlHttpClientPatchTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testPatchReturnsArrayWithCorrectKeys()
    {
        $client = new CurlHttpClient();

        $result = $client->request('PATCH', $this->baseUrl . '/patch', [], '');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testPatchReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->request('PATCH', $this->baseUrl . '/patch', [], '');

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testPatchWithJsonBody()
    {
        $client = new CurlHttpClient();

        $payload = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $headers = ['Content-Type' => 'application/json'];

        $result = $client->request('PATCH', $this->baseUrl . '/patch', $headers, $payload);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes back the data sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($payload, $body['data']);
    }

    public function testPatchWithCustomHeaders()
    {
        $client = new CurlHttpClient();

        $headers = [
            'User-Agent' => 'PHPUnit-Test-Patch',
            'X-Custom-Header' => 'CustomValue'
        ];

        $result = $client->request('PATCH', $this->baseUrl . '/patch', $headers, 'test data');

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test-Patch', $body['headers']['User-Agent']);
        $this->assertEquals('CustomValue', $body['headers']['X-Custom-Header']);
    }
}
