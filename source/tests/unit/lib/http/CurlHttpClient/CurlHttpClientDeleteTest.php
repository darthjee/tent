<?php

namespace Tent\Tests\Http;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpClient;

class CurlHttpClientDeleteTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testDeleteReturnsArrayWithCorrectKeys()
    {
        $client = new CurlHttpClient();

        $result = $client->request('DELETE', $this->baseUrl . '/delete', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testDeleteReturnsSuccessfulResponse()
    {
        $client = new CurlHttpClient();

        $result = $client->request('DELETE', $this->baseUrl . '/delete', []);

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testDeleteWithJsonBody()
    {
        $client = new CurlHttpClient();

        $payload = json_encode(['id' => 42]);
        $headers = ['Content-Type' => 'application/json'];

        $result = $client->request('DELETE', $this->baseUrl . '/delete', $headers, $payload);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes back the data sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($payload, $body['data']);
    }

    public function testDeleteWithCustomHeaders()
    {
        $client = new CurlHttpClient();

        $headers = [
            'User-Agent' => 'PHPUnit-Test-Delete',
            'X-Custom-Header' => 'CustomValue'
        ];

        $result = $client->request('DELETE', $this->baseUrl . '/delete', $headers);

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test-Delete', $body['headers']['User-Agent']);
        $this->assertEquals('CustomValue', $body['headers']['X-Custom-Header']);
    }
}
