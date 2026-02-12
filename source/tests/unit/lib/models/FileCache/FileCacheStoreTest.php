<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Models\Response;
use Tent\Models\Request;
use Tent\Models\FolderLocation;
use Tent\Utils\CacheFilePath;

class FileCacheStoreTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $request;
    private $headers;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cacheDir . '/*/*/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testStoreBodyAndHeaders()
    {
        $path = '/path/file.txt';
        $response = $this->buildResponse($path, 200, 'cached body');

        $cache = new FileCache($this->request, $this->location);

        $cache->store($response);

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $cache->headers());
    }

    public function testStoreCreatesDirectories()
    {
        $path = '/nested_dir/file.txt';
        $response = $this->buildResponse($path, 200, 'cached body');

        $cache = new FileCache($this->request, $this->location);

        $cache->store($response);

        $fullPath = $this->cacheDir . '/nested_dir/file.txt/GET';
        $this->assertTrue(is_dir($fullPath));
    }

    public function testStoreWithExistingDirectory()
    {
        $path = '/nested_dir/file.txt';
        $fullPath = $this->cacheDir . '/nested_dir/file.txt/GET';
        mkdir($fullPath, 0777, true);

        $response = $this->buildResponse($path, 200, 'some body');

        $cache = new FileCache($this->request, $this->location);

        $cache->store($response);

        $this->assertTrue(is_dir($fullPath));
        $this->assertTrue($cache->exists());
        $this->assertEquals('some body', $cache->content());
        $this->assertEquals($this->headers, $cache->headers());
    }

    public function testStoreFileContents()
    {
        $path = '/path/file.txt';
        $response = $this->buildResponse($path, 200, 'cached body');

        $cache = new FileCache($this->request, $this->location);
        $cache->store($response);

        $basePath = $this->cacheDir . '/path/file.txt/GET';
        $bodyPath = CacheFilePath::path('body', $basePath, $this->request->query());
        $metaPath = CacheFilePath::path('meta', $basePath, $this->request->query());

        $this->assertTrue(is_file($bodyPath), 'Body file does not exist or is not a file');
        $this->assertTrue(is_file($metaPath), 'Meta file does not exist or is not a file');

        $this->assertEquals('cached body', file_get_contents($bodyPath));
        $meta = json_decode(file_get_contents($metaPath), true);
        $this->assertEquals([
            'headers' => $this->headers,
            'httpCode' => 200
        ], $meta);
    }

    private function buildResponse(string $path, int $httpCode, string $body)
    {
        $this->headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $this->request = $this->buildRequest($path);

        return new Response([
            'body' => $body, 'httpCode' => $httpCode, 'headers' => $this->headers,
            'request' => $this->request
        ]);
    }

    private function buildRequest(string $path): Request
    {
        return new Request([
            'requestPath' => $path,
            'requestMethod' => 'GET'
        ]);
    }
}
