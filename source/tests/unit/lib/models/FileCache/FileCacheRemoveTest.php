<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheRemoveTest extends TestCase
{
    private string $cacheDir;
    private string $bodyPath;
    private string $metaPath;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_remove_test_' . uniqid();
        $fullPath = $this->cacheDir . '/some_file.txt/GET';
        mkdir($fullPath, 0777, true);

        $hash = hash('sha256', '');
        $this->bodyPath = CacheFilePath::path('body', $fullPath, $hash);
        $this->metaPath = CacheFilePath::path('meta', $fullPath, $hash);

        $request = new ProcessingRequest(['requestPath' => 'some_file.txt', 'requestMethod' => 'GET']);
        $this->cache = new FileCache($request, new FolderLocation($this->cacheDir));

        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRemoveDeletesBodyAndMetaFiles()
    {
        file_put_contents($this->bodyPath, 'body');
        file_put_contents($this->metaPath, '{}');
        $this->expectNoLog();

        $this->cache->remove();

        $this->assertFileDoesNotExist($this->bodyPath);
        $this->assertFileDoesNotExist($this->metaPath);
    }

    public function testRemoveIsSilentWhenFilesAlreadyGone()
    {
        $this->expectNoLog();

        $this->cache->remove();

        $this->assertFileDoesNotExist($this->bodyPath);
        $this->assertFileDoesNotExist($this->metaPath);
    }

    public function testRemoveIsSilentWhenOnlyOneFileExists()
    {
        file_put_contents($this->metaPath, '{}');
        $this->expectNoLog();

        $this->cache->remove();

        $this->assertFileDoesNotExist($this->metaPath);
    }

    private function expectNoLog(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->never())->method('log');
        Logger::setInstance($instance);
    }
}
