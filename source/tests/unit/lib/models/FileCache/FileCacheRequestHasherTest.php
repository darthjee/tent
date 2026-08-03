<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Cache\RequestHasher;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheRequestHasherTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $request;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_request_hasher_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        $this->request = new ProcessingRequest([
            'requestPath' => '/file.txt',
            'requestMethod' => 'GET',
            'query' => 'a=1',
        ]);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
    }

    public function testWithoutHasherFallsBackToQueryRequestHasher()
    {
        $cache = new FileCache($this->request, $this->location);

        $expectedHash = hash('sha256', 'a=1');
        $basePath = $this->cacheDir . '/file.txt/GET';
        $expectedMetaPath = CacheFilePath::path('meta', $basePath, $expectedHash);

        $this->assertEquals($expectedMetaPath, $cache->metaFilePath());
    }

    public function testCustomHasherDrivesBothBodyAndMetaFilePaths()
    {
        $hasher = $this->buildHasherMock('custom-hash');

        $cache = new FileCache($this->request, $this->location, $hasher);

        $basePath = $this->cacheDir . '/file.txt/GET';
        $expectedMetaPath = CacheFilePath::path('meta', $basePath, 'custom-hash');
        $this->assertEquals($expectedMetaPath, $cache->metaFilePath());

        $response = new \Tent\Models\Response([
            'body' => 'body',
            'httpCode' => 200,
            'headers' => [],
            'request' => $this->request,
        ]);
        $cache->store($response);

        $this->assertTrue($cache->exists());
        $this->assertTrue(is_file(CacheFilePath::path('body', $basePath, 'custom-hash')));
        $this->assertTrue(is_file(CacheFilePath::path('meta', $basePath, 'custom-hash')));
    }

    public function testCustomHasherIsCalledAtMostOncePerConstruction()
    {
        $hasher = $this->createMock(RequestHasher::class);
        $hasher->expects($this->once())
            ->method('hash')
            ->with($this->request)
            ->willReturn('custom-hash');

        new FileCache($this->request, $this->location, $hasher);
    }

    public function testHasherIsNeverCalledWhenCacheHashIsAlreadyMemoized()
    {
        $this->request->setCacheHash('already-memoized');

        $hasher = $this->createMock(RequestHasher::class);
        $hasher->expects($this->never())->method('hash');

        $cache = new FileCache($this->request, $this->location, $hasher);

        $basePath = $this->cacheDir . '/file.txt/GET';
        $expectedMetaPath = CacheFilePath::path('meta', $basePath, 'already-memoized');
        $this->assertEquals($expectedMetaPath, $cache->metaFilePath());
    }

    private function buildHasherMock(string $hash): RequestHasher
    {
        $hasher = $this->createMock(RequestHasher::class);
        $hasher->method('hash')->willReturn($hash);

        return $hasher;
    }
}
