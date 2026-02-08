<?php

namespace Tent\Tests\Service\ResponseContentReader;

use PHPUnit\Framework\TestCase;
use Tent\Service\ResponseContentReader;
use Tent\Content\FileCache;
use Tent\Models\Request;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Utils\CacheFilePath;

class ResponseContentReaderWithFileCacheTest extends TestCase
{
    private $testDir;
    private $body;
    private $meta;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tent_filecache_reader_' . uniqid();
        mkdir($this->testDir);
        $this->body = 'Cached body from cache';
        $this->meta = [
            'headers' => ['Content-Type: text/plain', 'X-Test: yes'],
            'httpCode' => 207
        ];
        $fullPath = $this->testDir . '/file.txt';
        $bodyPath = CacheFilePath::path('body', $fullPath, 'GET', '');
        $metaPath = CacheFilePath::path('meta', $fullPath, 'GET', '');

        mkdir($fullPath, 0777, true);
        file_put_contents($bodyPath, $this->body);
        file_put_contents($metaPath, json_encode($this->meta));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetResponseReturnsCacheContentAndMeta()
    {
        $location = new FolderLocation($this->testDir);
        $request = new Request(['requestPath' => '/file.txt', 'requestMethod' => 'GET']);
        $cache = new FileCache($request, $location);
        $reader = new ResponseContentReader($request, $cache);

        $response = $reader->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($this->body, $response->body());
        $this->assertEquals(207, $response->httpCode());
        $this->assertEquals($this->meta['headers'], $response->headerLines());
    }
}
