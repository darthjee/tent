<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;

class IndexIntegrationTest extends TestCase
{
    private const BASE_URL = 'http://localhost:80';

    private function makeRequest(string $path): array
    {
        $url = self::BASE_URL . $path;
        $response = file_get_contents($url);

        if ($response === false) {
            $this->fail("Failed to make request to: {$url}");
        }

        return json_decode($response, true);
    }

    public function testRootPathPassesThroughIndex()
    {
        $response = $this->makeRequest('/');

        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('/', $response['uri']);
    }

    public function testArbitraryPathPassesThroughIndex()
    {
        $response = $this->makeRequest('/some/random/path');

        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('/some/random/path', $response['uri']);
    }

    public function testFilePathPassesThroughIndex()
    {
        $response = $this->makeRequest('/test.txt');

        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('/test.txt', $response['uri']);
    }

    public function testDirectoryPathPassesThroughIndex()
    {
        $response = $this->makeRequest('/admin/');

        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('/admin/', $response['uri']);
    }

    public function testNestedPathPassesThroughIndex()
    {
        $response = $this->makeRequest('/api/v1/users/123');

        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('/api/v1/users/123', $response['uri']);
    }

    /**
     * @dataProvider pathProvider
     */
    public function testAllPathsReturnSameStructure(string $path)
    {
        $response = $this->makeRequest($path);

        // All responses should have the same structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('method', $response);
        $this->assertArrayHasKey('uri', $response);
        $this->assertArrayHasKey('timestamp', $response);

        // All responses should have status 'ok'
        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('Request received', $response['message']);
        $this->assertEquals('GET', $response['method']);
        $this->assertEquals($path, $response['uri']);
    }

    public function pathProvider(): array
    {
        return [
            'root' => ['/'],
            'simple path' => ['/test'],
            'nested path' => ['/api/users'],
            'deep nested path' => ['/api/v1/users/123/posts'],
            'file extension' => ['/image.jpg'],
            'query string' => ['/search?q=test'],
            'with trailing slash' => ['/admin/'],
            'special characters' => ['/users/joão'],
        ];
    }
}
