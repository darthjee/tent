<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheTimestampTest extends TestCase
{
    private $basePath;
    private $path;
    private $fullPath;
    private $request;
    private $location;
    private $hash;

    public function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/tent_cache_timestamp_' . uniqid();
        $this->path = 'some_file.txt';
        $this->fullPath = $this->basePath . '/' . $this->path . '/GET';
        $this->request = new ProcessingRequest(['requestPath' => $this->path, 'requestMethod' => 'GET']);
        $this->location = new FolderLocation($this->basePath);
        $this->hash = hash('sha256', '');

        mkdir($this->fullPath, 0777, true);
    }

    public function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->basePath);
    }

    public function testTimestampReturnsStoredValue()
    {
        $timestamp = mktime(12, 30, 0, 6, 15, 2025);
        $meta = ['headers' => [], 'httpCode' => 200, 'timestamp' => $timestamp];
        file_put_contents(CacheFilePath::path('body', $this->fullPath, $this->hash), 'body');
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, $this->hash), json_encode($meta));

        $cache = new FileCache($this->request, $this->location);

        $this->assertEquals($timestamp, $cache->timestamp());
    }

    public function testTimestampReturnsNullWhenMetaFileIsMissing()
    {
        $cache = new FileCache($this->request, $this->location);

        $this->assertNull($cache->timestamp());
    }

    public function testTimestampReturnsNullWhenTimestampKeyIsMissing()
    {
        $meta = ['headers' => [], 'httpCode' => 200];
        file_put_contents(CacheFilePath::path('meta', $this->fullPath, $this->hash), json_encode($meta));

        $cache = new FileCache($this->request, $this->location);

        $this->assertNull($cache->timestamp());
    }

    public function testRemoveDeletesBodyAndMetaFiles()
    {
        $meta = ['headers' => [], 'httpCode' => 200, 'timestamp' => time()];
        $bodyPath = CacheFilePath::path('body', $this->fullPath, $this->hash);
        $metaPath = CacheFilePath::path('meta', $this->fullPath, $this->hash);
        file_put_contents($bodyPath, 'body');
        file_put_contents($metaPath, json_encode($meta));

        $cache = new FileCache($this->request, $this->location);
        $cache->remove();

        $this->assertFileDoesNotExist($bodyPath);
        $this->assertFileDoesNotExist($metaPath);
        $this->assertFalse($cache->exists());
    }

    public function testRemoveDoesNotThrowWhenFilesDoNotExist()
    {
        $cache = new FileCache($this->request, $this->location);

        $this->expectNotToPerformAssertions();
        $cache->remove();
    }

    public function testMetaFilePathReturnsExpectedPath()
    {
        $cache = new FileCache($this->request, $this->location);
        $expected = CacheFilePath::path('meta', $this->fullPath, $this->hash);

        $this->assertEquals($expected, $cache->metaFilePath());
    }
}
