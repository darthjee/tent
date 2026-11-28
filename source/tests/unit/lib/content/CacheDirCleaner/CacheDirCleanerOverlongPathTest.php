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
use Tent\Tests\Support\Utils\PhpWarningCatcher;

class CacheDirCleanerOverlongPathTest extends TestCase
{
    private string $cacheDir;

    private CacheDirCleaner $cleaner;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_dir_cleaner_overlong_test_' . uniqid();
        // The trash folder, which any cleanup may create, is there beforehand.
        mkdir($this->cacheDir . '/.tent-trash', 0777, true);
        foreach (['a', 'other'] as $name) {
            mkdir($this->cacheDir . '/' . $name . '/GET', 0777, true);
            file_put_contents($this->cacheDir . '/' . $name . '/GET/entry.body.dat', 'body');
        }

        Logger::setInstance(new NullLoggerInstance());
        $this->cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));

        $this->catcher = new PhpWarningCatcher();
        $this->catcher->start();
    }

    protected function assertPostConditions(): void
    {
        $this->assertSame([], $this->catcher->warnings());
    }

    protected function tearDown(): void
    {
        $this->catcher->stop();
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider overlongPathProvider
     */
    public function testCleanRemovesNothing(string $path)
    {
        $before = $this->listTree();

        $this->cleaner->clean('collection', $path);
        $this->cleaner->clean('entity', $path);

        $this->assertSame($before, $this->listTree());
    }

    /**
     * @dataProvider overlongPathProvider
     */
    public function testCleanPathRemovesNothing(string $path)
    {
        $before = $this->listTree();

        $this->cleaner->cleanPath($path);

        $this->assertSame($before, $this->listTree());
    }

    public function overlongPathProvider(): array
    {
        return [
            'segment of 300 bytes' => ['/' . str_repeat('s', 300)],
            'nested segment of 300 bytes' => ['/a/' . str_repeat('s', 300) . '/1'],
            'path longer than 4096 bytes' => [str_repeat('/' . str_repeat('s', 200), 25)],
        ];
    }

    public function testOverlongDirectoryUnderAnExistingParentIsIgnored()
    {
        // The parent can be created, while its `GET` directory is too long.
        $remaining = 4093 - strlen($this->cacheDir . '/');
        $segments = [];
        while ($remaining > 201) {
            $segments[] = str_repeat('s', 200);
            $remaining -= 201;
        }
        $segments[] = str_repeat('s', $remaining);
        $path = '/' . implode('/', $segments);
        mkdir($this->cacheDir . $path, 0777, true);
        $before = $this->listTree();

        $this->cleaner->clean('entity', $path);
        $this->cleaner->cleanPath($path);

        $this->assertDirectoryExists($this->cacheDir . $path);
        $this->assertSame($before, $this->listTree());
    }

    private function listTree(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = array_keys(iterator_to_array($iterator));
        sort($paths);

        return $paths;
    }
}
