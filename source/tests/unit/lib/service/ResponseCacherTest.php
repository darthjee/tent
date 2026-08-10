<?php

namespace Tent\Tests\Service;

require_once __DIR__ . '/../../../support/loader.php';


use PHPUnit\Framework\TestCase;
use Tent\Service\ResponseCacher;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

class ResponseCacherTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $headers;
    private $request;
    private $path;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/response_cacher_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testProcessStoresCache()
    {
        $response = $this->buildResponse(200);
        $cache = new FileCache($this->request, $this->location);

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertTrue($cache->exists());
        $this->assertEquals('cached body', $cache->content());
        foreach ($this->headers as $header) {
            $this->assertContains($header, $cache->headers());
        }
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

        $hash = hash('sha256', '');
        $bodyFile = CacheFilePath::path('body', $this->cacheDir . '/file.txt', $hash);
        $metaFile = CacheFilePath::path('meta', $this->cacheDir . '/file.txt', $hash);
        mkdir(dirname($bodyFile), 0777, true);
        file_put_contents($bodyFile, 'original body');
        file_put_contents($metaFile, json_encode(['headers' => ["Header1: original", "Header2: value"]]));

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertEquals('original body', file_get_contents($bodyFile));
        $meta = json_decode(file_get_contents($metaFile), true);
        $this->assertEquals(["Header1: original", "Header2: value"], $meta['headers']);
    }

    public function testProcessStoresFilteredHeadersWhenHeaderFilterConfigured()
    {
        $response = $this->buildResponse(200);
        $response->setHeaders(array_merge($this->headers, ['Set-Cookie: session=abc']));
        $cache = new FileCache($this->request, $this->location);

        $cacher = new ResponseCacher($cache, $response, new ExcludedHeaderFilter(['Set-Cookie']));
        $cacher->process();

        $this->assertTrue($cache->exists());
        foreach ($this->headers as $header) {
            $this->assertContains($header, $cache->headers());
        }
        $this->assertNotContains('Set-Cookie: session=abc', $cache->headers());
    }

    public function testProcessStoresUnfilteredHeadersWhenHeaderFilterIsNull()
    {
        $response = $this->buildResponse(200);
        $response->setHeaders(array_merge($this->headers, ['Set-Cookie: session=abc']));
        $cache = new FileCache($this->request, $this->location);

        $cacher = new ResponseCacher($cache, $response);
        $cacher->process();

        $this->assertTrue($cache->exists());
        $this->assertContains('Set-Cookie: session=abc', $cache->headers());
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
