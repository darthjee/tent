<?php

namespace Tent\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tent\Service\ResponseCacher;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Models\FileCache;
use Tent\Models\FolderLocation;
use Tent\Utils\CacheFilePath;

class ResponseCacherTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $headers;
    private $cache;
    private $request;
    private $path;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/response_cacher_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cacheDir . '/*/*'));
        array_map('rmdir', glob($this->cacheDir . '/*'));
        rmdir($this->cacheDir);
    }

    public function testProcessStoresCache()
    {
        $response = $this->buildResponse(200);
        $cache = new FileCache($this->request, $this->location);

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        $this->assertEquals($this->headers, $cache->headers());
    }

    public function testProcessDoesNotStoreCacheForWrongCode()
    {
        $response = $this->buildResponse(403);
        $cache = new FileCache($this->request, $this->location);

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertTrue($cache->exists());
    }

    public function testProcessDoesNotOverwriteExistingCache()
    {
        $response = $this->buildResponse(200);
        $cache = new FileCache($this->request, $this->location);

        $bodyFile = CacheFilePath::path('body', $this->cacheDir . '/file.txt', '');
        $metaFile = CacheFilePath::path('meta', $this->cacheDir . '/file.txt', '');
        mkdir(dirname($bodyFile), 0777, true);
        file_put_contents($bodyFile, 'original body');
        file_put_contents($metaFile, "Header1: original\nHeader2: value");

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertEquals('original body', file_get_contents($bodyFile));
        $this->assertEquals("Header1: original\nHeader2: value", file_get_contents($metaFile));
    }

    private function buildResponse(int $httpCode)
    {
        $this->path = '/file.txt';
        $this->headers = ['Content-Type: text/plain', 'Content-Length: 11'];
        $this->request = $this->buildRequest($this->path, 'GET');

        return new Response([
            'body' => 'cached body', 'httpCode' => $httpCode, 'headers' => $this->headers,
            'request' => $this->request
        ]);
    }

    private function buildRequest(string $path, string $method): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath' => $path,
            'requestMethod' => $method
        ]);
    }
}
