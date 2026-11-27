<?php

namespace Tent\Tests\Content\CacheDirCleaner;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheDirCleaner;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
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
        mkdir($outsideDir . '/GET', 0777, true);
        file_put_contents($outsideDir . '/GET/entry.body', 'content');

        try {
            $this->cleaner->cleanPath('/../' . basename($outsideDir));

            $this->assertFileExists($outsideDir . '/GET/entry.body');
        } finally {
            FileSystemUtils::removeDirRecursive($outsideDir);
        }
    }

    /**
     * @dataProvider unsafePathProvider
     */
    public function testCleanPathRefusesUnsafePathWithoutDeletingAnything(string $path)
    {
        $outsideName = basename($this->cacheDir) . '_target';
        $path = str_replace('{outside}', $outsideName, $path);
        $outsideDir = dirname($this->cacheDir) . '/' . $outsideName;
        $planted = [
            $outsideDir . '/GET/entry.body',
            $this->cacheDir . '/a/GET/entry.body',
            $this->cacheDir . '/b/GET/entry.body',
            $this->cacheDir . '/a/b/GET/entry.body',
        ];
        foreach ($planted as $file) {
            mkdir(dirname($file), 0777, true);
            file_put_contents($file, 'content');
        }
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        try {
            $this->cleaner->cleanPath($path);

            foreach ($planted as $file) {
                $this->assertStringEqualsFile($file, 'content');
            }
            $this->assertDirectoryDoesNotExist($this->cacheDir . '/.tent-trash');
            $this->assertSame(
                [['warn', 'cache cleanup refused — path: ' . $path . ', reason: unsafe path']],
                $logger->lines
            );
        } finally {
            FileSystemUtils::removeDirRecursive($outsideDir);
        }
    }

    public function testCleanPathStillSweepsAnOrphanForUnsafePath()
    {
        $orphan = $this->cacheDir . '/.tent-trash/1.0123456789abcdef';
        mkdir($orphan, 0777, true);

        $this->cleaner->cleanPath('/a/..');

        $this->assertDirectoryDoesNotExist($orphan);
    }

    public function unsafePathProvider(): array
    {
        return [
            'above the root' => ['/../{outside}'],
            'staying inside the root' => ['/a/../b'],
            'current segment' => ['/a/./b'],
            'trailing current segment' => ['/a/.'],
            'NUL byte' => ["/a/b\0c"],
        ];
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
