<?php

namespace Tent\Tests\Http\CurlHttpExecutor;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpExecutor\Patch;

class PatchTest extends TestCase
{
    private $baseUrl = 'http://httpbin';

    public function testRequestReturnsArrayWithCorrectKeys()
    {
        $executor = new Patch([
            'url' => $this->baseUrl . '/patch',
            'headers' => [],
            'body' => ''
        ]);

        $result = $executor->request();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('httpCode', $result);
        $this->assertArrayHasKey('headers', $result);
    }

    public function testRequestReturnsSuccessfulResponse()
    {
        $executor = new Patch([
            'url' => $this->baseUrl . '/patch',
            'headers' => [],
            'body' => ''
        ]);

        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);
        $this->assertNotEmpty($result['body']);
    }

    public function testRequestWithJsonBody()
    {
        $payload = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $headers = ['Content-Type' => 'application/json'];

        $executor = new Patch([
            'url' => $this->baseUrl . '/patch',
            'headers' => $headers,
            'body' => $payload
        ]);

        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes back the data sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($payload, $body['data']);
    }

    public function testRequestWithCustomHeaders()
    {
        $headers = [
            'User-Agent' => 'PHPUnit-Test-Patch',
            'X-Custom-Header' => 'CustomValue'
        ];

        $executor = new Patch([
            'url' => $this->baseUrl . '/patch',
            'headers' => $headers,
            'body' => 'test data'
        ]);

        $result = $executor->request();

        $this->assertEquals(200, $result['httpCode']);

        // httpbin echoes headers back, verify they were sent
        $body = json_decode($result['body'], true);
        $this->assertArrayHasKey('headers', $body);
        $this->assertEquals('PHPUnit-Test-Patch', $body['headers']['User-Agent']);
        $this->assertEquals('CustomValue', $body['headers']['X-Custom-Header']);
    }

    public function testRequestReturnsHeadersArray()
    {
        $executor = new Patch([
            'url' => $this->baseUrl . '/patch',
            'headers' => [],
            'body' => 'test'
        ]);

        $result = $executor->request();

        $this->assertIsArray($result['headers']);
        $this->assertNotEmpty($result['headers']);

        // Verify headers are in correct format (key: value)
        foreach ($result['headers'] as $header) {
            $this->assertStringContainsString(':', $header);
        }
    }
}
