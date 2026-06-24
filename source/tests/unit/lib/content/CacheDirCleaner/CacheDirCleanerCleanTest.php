<?php

namespace Tent\Tests\Content\CacheDirCleaner;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheDirCleaner;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheDirCleanerCleanTest extends TestCase
{
    private string $cacheDir;
    private CacheDirCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_dir_cleaner_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testCleanDeletesCollectionDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $this->cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($collectionDir);
    }

    public function testCleanDeletesCollectionDirForEntityPath()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $this->cleaner->clean('collection', '/users/1');

        $this->assertDirectoryDoesNotExist($collectionDir);
    }

    public function testCleanDeletesEntityDir()
    {
        $entityDir = $this->cacheDir . '/users/1/GET';
        mkdir($entityDir, 0777, true);

        $this->cleaner->clean('entity', '/users/1');

        $this->assertDirectoryDoesNotExist($entityDir);
    }

    public function testCleanEntityDoesNothingForSingleSegmentPath()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $this->cleaner->clean('entity', '/users');

        $this->assertDirectoryExists($collectionDir);
    }

    public function testCleanDoesNothingWhenDirDoesNotExist()
    {
        $this->expectNotToPerformAssertions();

        $this->cleaner->clean('collection', '/users');
    }

    public function testCleanDoesNothingForUnknownTarget()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $this->cleaner->clean('unknown', '/users');

        $this->assertDirectoryExists($collectionDir);
    }

    public function testCleanDoesNotDeleteUnrelatedDirs()
    {
        $unrelatedDir = $this->cacheDir . '/products/GET';
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($unrelatedDir, 0777, true);
        mkdir($collectionDir, 0777, true);

        $this->cleaner->clean('collection', '/users');

        $this->assertDirectoryExists($unrelatedDir);
        $this->assertDirectoryDoesNotExist($collectionDir);
    }

    public function testCleanLogsDebugMessageOnDeletion()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with($this->stringContains('cache cleared'), 'debug');
        Logger::setInstance($instance);

        $this->cleaner->clean('collection', '/users');
    }

    public function testCleanDoesNotLogWhenDirDoesNotExist()
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->never())->method('log');
        Logger::setInstance($instance);

        $this->cleaner->clean('collection', '/users');
    }

    public function testCleanDoesNotDeleteDirOutsideBaseLocation()
    {
        $outsideDir = sys_get_temp_dir() . '/cache_dir_cleaner_outside_' . uniqid();
        mkdir($outsideDir, 0777, true);

        try {
            $escapingLocation = new FolderLocation($this->cacheDir . '/users/../..');
            $cleaner = new CacheDirCleaner($escapingLocation);

            $cleaner->clean('collection', basename($outsideDir));

            $this->assertDirectoryExists($outsideDir);
        } finally {
            FileSystemUtils::removeDirRecursive($outsideDir);
        }
    }
}
