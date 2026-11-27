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

class CacheDirCleanerGuardTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cache_dir_cleaner_guard_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
        mkdir($this->cacheDir, 0777, true);
        mkdir($this->outsideDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRefusesParentSegmentLeavingTheCache()
    {
        $survivor = $this->plantFile($this->testDir . '/GET');
        $this->expectRefusal($this->cacheDir . '/../GET', 'dot segment');

        $this->cleaner()->cleanPath('/..');

        $this->assertFileExists($survivor);
    }

    public function testRefusesParentSegmentInCollectionTarget()
    {
        $survivor = $this->plantFile($this->testDir . '/GET');
        $this->expectRefusal($this->cacheDir . '/../GET', 'dot segment');

        $this->cleaner()->clean('collection', '/../x');

        $this->assertFileExists($survivor);
    }

    public function testRefusesParentSegmentStayingInsideTheCache()
    {
        mkdir($this->cacheDir . '/games');
        $survivor = $this->plantFile($this->cacheDir . '/users.json/GET');
        $this->expectRefusal($this->cacheDir . '/games/../users.json/GET', 'dot segment');

        $this->cleaner()->cleanPath('/games/../users.json');

        $this->assertFileExists($survivor);
    }

    public function testRefusesCurrentDirSegment()
    {
        $survivor = $this->plantFile($this->cacheDir . '/users.json/GET');
        $this->expectRefusal($this->cacheDir . '/./users.json/GET', 'dot segment');

        $this->cleaner()->cleanPath('/./users.json');

        $this->assertFileExists($survivor);
    }

    /**
     * @dataProvider dotLikeNameProvider
     */
    public function testDeletesDirWhoseNameOnlyLooksLikeTraversal(string $name)
    {
        $dir = $this->cacheDir . '/' . $name . '/GET';
        $this->plantFile($dir);
        $this->expectCleared($dir);

        $this->cleaner()->cleanPath('/' . $name);

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function dotLikeNameProvider(): array
    {
        return [
            'three dots' => ['...'],
            'leading dots' => ['..foo'],
            'trailing dots' => ['foo..'],
            'encoded dots' => ['%2e%2e'],
        ];
    }

    public function testRefusesNulByteWithoutThrowing()
    {
        $this->expectRefusal($this->cacheDir . "/users\0.json/GET", 'dot segment');

        $this->cleaner()->cleanPath("/users\0.json");
    }

    public function testCleansWhenBaseContainsParentSegment()
    {
        mkdir($this->cacheDir . '/sub');
        $base = $this->cacheDir . '/sub/..';
        $this->plantFile($this->cacheDir . '/users.json/GET');
        $this->expectCleared($base . '/users.json/GET');

        $this->cleaner($base)->cleanPath('/users.json');

        $this->assertDirectoryDoesNotExist($this->cacheDir . '/users.json/GET');
    }

    public function testCleansWhenBaseHasTrailingSlash()
    {
        $dir = $this->cacheDir . '/users.json/GET';
        $this->plantFile($dir);
        $this->expectCleared($dir);

        $this->cleaner($this->cacheDir . '/')->cleanPath('/users.json');

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testRefusesRootAsCacheLocation()
    {
        $dir = $this->outsideDir . '/GET';
        $survivor = $this->plantFile($dir);
        $this->expectRefusal($dir, 'misconfigured cache location');

        $this->cleaner('/')->cleanPath($this->outsideDir);

        $this->assertFileExists($survivor);
    }

    public function testRefusesIntermediateSymlinkLeavingTheCache()
    {
        $survivor = $this->plantFile($this->outsideDir . '/x/GET');
        symlink($this->outsideDir, $this->cacheDir . '/link');
        $this->expectRefusal(
            $this->cacheDir . '/link/x/GET',
            'resolves outside cache location (' . realpath($this->outsideDir . '/x') . ')'
        );

        $this->cleaner()->cleanPath('/link/x');

        $this->assertFileExists($survivor);
    }

    public function testRefusesIntermediateSymlinkToSiblingSharingTheBasePrefix()
    {
        $sibling = $this->testDir . '/cache-sibling';
        $survivor = $this->plantFile($sibling . '/GET');
        symlink($sibling, $this->cacheDir . '/link');
        $this->expectRefusal(
            $this->cacheDir . '/link/GET',
            'resolves outside cache location (' . realpath($sibling) . ')'
        );

        $this->cleaner()->cleanPath('/link');

        $this->assertFileExists($survivor);
    }

    public function testRefusesDirSwappedForSymlinkByAnotherProcess()
    {
        $parent = $this->cacheDir . '/users';
        mkdir($parent . '/1', 0777, true);
        $survivor = $this->plantFile($this->outsideDir . '/1/GET');
        // Primes the realpath cache with the real directory.
        $this->assertNotFalse(realpath($parent . '/1'));
        // Shell commands leave the realpath cache of this process untouched.
        exec('mv ' . escapeshellarg($parent) . ' ' . escapeshellarg($this->testDir . '/moved'));
        exec('ln -s ' . escapeshellarg($this->outsideDir) . ' ' . escapeshellarg($parent));
        $this->expectRefusal(
            $parent . '/1/GET',
            'resolves outside cache location (' . realpath($this->outsideDir) . '/1)'
        );

        $this->cleaner()->clean('entity', '/users/1');

        $this->assertFileExists($survivor);
    }

    public function testCleansThroughSymlinkedBase()
    {
        $link = $this->testDir . '/cachelink';
        symlink($this->cacheDir, $link);
        $this->plantFile($this->cacheDir . '/users/GET');
        $this->expectCleared($link . '/users/GET');

        $this->cleaner($link)->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($this->cacheDir . '/users/GET');
    }

    public function testUnlinksSymlinkedRootInsideSymlinkedBase()
    {
        $link = $this->testDir . '/cachelink';
        symlink($this->cacheDir, $link);
        $survivor = $this->plantFile($this->outsideDir . '/precious');
        mkdir($this->cacheDir . '/users');
        symlink($this->outsideDir, $this->cacheDir . '/users/GET');
        $this->expectCleared($link . '/users/GET');

        $this->cleaner($link)->clean('collection', '/users');

        $this->assertFalse(is_link($this->cacheDir . '/users/GET'));
        $this->assertFileExists($survivor);
    }

    public function testIsSilentWhenParentDoesNotExist()
    {
        $this->expectNoLog();

        $this->cleaner()->clean('collection', '/missing/1');

        $this->assertDirectoryDoesNotExist($this->cacheDir . '/missing');
    }

    public function testIsSilentWhenBaseDoesNotExist()
    {
        $base = $this->testDir . '/missing';
        $this->expectNoLog();

        $this->cleaner($base)->cleanPath('/users.json');

        $this->assertDirectoryDoesNotExist($base);
    }

    public function testCleansDirDirectlyUnderTheBase()
    {
        $this->plantFile($this->cacheDir . '/GET');
        // The resolver joins the empty request path as a double slash.
        $this->expectCleared($this->cacheDir . '//GET');

        $this->cleaner()->cleanPath('/');

        $this->assertDirectoryDoesNotExist($this->cacheDir . '/GET');
    }

    private function cleaner(?string $base = null): CacheDirCleaner
    {
        return new CacheDirCleaner(new FolderLocation($base ?? $this->cacheDir));
    }

    /**
     * Creates a cache directory holding one file, returning the path of the file.
     */
    private function plantFile(string $dir): string
    {
        mkdir($dir, 0777, true);
        $file = $dir . '/entry.body';
        file_put_contents($file, 'content');

        return $file;
    }

    private function expectRefusal(string $dir, string $reason): void
    {
        $this->expectSingleLog('cache cleanup refused — dir: ' . $dir . ', reason: ' . $reason, 'warn');
    }

    private function expectCleared(string $dir): void
    {
        $this->expectSingleLog('cache cleared — dir: ' . $dir, 'debug');
    }

    private function expectSingleLog(string $message, string $level): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with($message, $level);
        Logger::setInstance($instance);
    }

    private function expectNoLog(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->never())->method('log');
        Logger::setInstance($instance);
    }
}
