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

class CacheDirCleanerCleanPathTest extends TestCase
{
    private string $cacheDir;
    private CacheDirCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_dir_cleaner_clean_path_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testCleanPathDeletesExistingDir()
    {
        $dir = $this->cacheDir . '/games.json/GET';
        mkdir($dir, 0777, true);

        $this->cleaner->cleanPath('/games.json');

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testCleanPathDeletesNestedDir()
    {
        $dir = $this->cacheDir . '/games/space-invaders.json/GET';
        mkdir($dir, 0777, true);

        $this->cleaner->cleanPath('/games/space-invaders.json');

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testCleanPathDoesNothingWhenDirDoesNotExist()
    {
        $this->expectNotToPerformAssertions();

        $this->cleaner->cleanPath('/games.json');
    }

    public function testCleanPathDoesNotDeleteUnrelatedDirs()
    {
        $unrelatedDir = $this->cacheDir . '/products.json/GET';
        $targetDir = $this->cacheDir . '/games.json/GET';
        mkdir($unrelatedDir, 0777, true);
        mkdir($targetDir, 0777, true);

        $this->cleaner->cleanPath('/games.json');

        $this->assertDirectoryExists($unrelatedDir);
        $this->assertDirectoryDoesNotExist($targetDir);
    }

    public function testCleanPathDoesNotDeleteDirOutsideBaseLocation()
    {
        $outsideDir = sys_get_temp_dir() . '/cache_dir_cleaner_clean_path_outside_' . uniqid();
        mkdir($outsideDir, 0777, true);

        try {
            $escapingLocation = new FolderLocation($this->cacheDir . '/games/../..');
            $cleaner = new CacheDirCleaner($escapingLocation);

            $cleaner->cleanPath(basename($outsideDir));

            $this->assertDirectoryExists($outsideDir);
        } finally {
            FileSystemUtils::removeDirRecursive($outsideDir);
        }
    }

    public function testCleanPathLogsDebugMessageOnDeletion()
    {
        $dir = $this->cacheDir . '/games.json/GET';
        mkdir($dir, 0777, true);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with($this->stringContains('cache cleared'), 'debug');
        Logger::setInstance($instance);

        $this->cleaner->cleanPath('/games.json');
    }
}
