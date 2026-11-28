<?php

namespace Tent\Tests\Service\CacheContentReader;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\File;
use Tent\Content\FileCache;
use Tent\Exceptions\FileNotFoundException;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Service\CacheContentReader;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheContentReaderTest extends TestCase
{
    private string $parentDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->parentDir = sys_get_temp_dir() . '/cache_content_reader_test_' . uniqid();
        $this->cacheDir = $this->parentDir . '/cache';
        mkdir($this->cacheDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->parentDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testGetResponseReturnsTheEntryOfASafeLookAlikePath()
    {
        $request = $this->buildRequest('/a..b');
        $cache = new FileCache($request, new FolderLocation($this->cacheDir));
        $cache->store(new Response([
            'body' => 'cached body',
            'httpCode' => 207,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]));

        $response = (new CacheContentReader($request, $cache))->getResponse();

        $this->assertSame('cached body', $response->body());
        $this->assertSame(207, $response->httpCode());
        $this->assertContains('Content-Type: text/plain', $response->headers());
    }

    public function testGetResponseNeverReadsAnUnsafeEntry()
    {
        $request = $this->buildRequest('/../x');
        $cache = new FileCache($request, new FolderLocation($this->cacheDir));
        $metaPath = $cache->metaFilePath();
        mkdir(dirname($metaPath), 0777, true);
        file_put_contents(str_replace('.meta.json', '.body.dat', $metaPath), 'planted body');
        file_put_contents($metaPath, json_encode(['headers' => [], 'httpCode' => 200]));

        $this->expectException(FileNotFoundException::class);
        (new CacheContentReader($request, $cache))->getResponse();
    }

    public function testDoesNotAcceptStaticFiles()
    {
        $file = new File('/a..b', new FolderLocation($this->cacheDir));

        $this->expectException(\TypeError::class);
        new CacheContentReader($this->buildRequest('/a..b'), $file);
    }

    private function buildRequest(string $path): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => $path, 'requestMethod' => 'GET']);
    }
}
