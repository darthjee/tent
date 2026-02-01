<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Service\ResponseContentReader;
use Tent\Models\FileCache;
use Tent\Models\Request;
use Tent\Models\FolderLocation;
use Tent\Models\Response;

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
        mkdir($fullPath, 0777, true);
        file_put_contents($fullPath . '/0000000000000000000000000000000000000000000000000000000000000000.body.txt', $this->body);
        file_put_contents($fullPath . '/0000000000000000000000000000000000000000000000000000000000000000.meta.json', json_encode($this->meta));
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
        $request = new Request(['requestPath' => '/file.txt']);
        $cache = new FileCache($request, $location);
        $reader = new ResponseContentReader($request, $cache);

        $response = $reader->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($this->body, $response->body());
        $this->assertEquals(207, $response->httpCode());
        $this->assertEquals($this->meta['headers'], $response->headerLines());
    }
}
