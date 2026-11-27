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

class CacheDirCleanerRenameTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;
    private string $targetDir;
    private string $trashDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cache_dir_cleaner_rename_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
        $this->targetDir = $this->cacheDir . '/users/GET';
        $this->trashDir = $this->cacheDir . '/.tent-trash';
        mkdir($this->cacheDir, 0777, true);
        mkdir($this->outsideDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        if (is_link($this->trashDir)) {
            unlink($this->trashDir);
        }
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testMovesTargetIntoTrashAndDeletesIt()
    {
        $this->buildTree([$this->targetDir . '/entry.body', $this->targetDir . '/nested/deep.body']);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertCount(1, $cleaner->renames);
        [$from, $to] = $cleaner->renames[0];
        $this->assertSame($this->targetDir, $from);
        $this->assertMatchesRegularExpression(
            '#^' . preg_quote($this->trashDir . '/', '#') . '\d+\.[0-9a-f]{16}$#',
            $to
        );
        $this->assertSame([$to], $cleaner->walkedDirs);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertTrashIsEmpty();
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testNamesTrashEntryAfterTheCurrentTime()
    {
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(null, 1700000000);

        $cleaner->clean('collection', '/users');

        $this->assertStringStartsWith('1700000000.', basename($cleaner->renames[0][1]));
    }

    public function testCustomTargetIsInvalidatedThroughTheTrash()
    {
        $dir = $this->cacheDir . '/games.json/GET';
        $this->buildTree([$dir . '/entry.body']);
        $cleaner = $this->seamedCleaner();

        $cleaner->cleanPath('/games.json');

        $this->assertSame($dir, $cleaner->renames[0][0]);
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertTrashIsEmpty();
    }

    public function testReusesExistingTrashFolder()
    {
        mkdir($this->trashDir);
        $this->buildTree([$this->targetDir . '/entry.body']);
        $logger = $this->captureLogs();

        $this->seamedCleaner()->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertTrashIsEmpty();
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testLostRaceIsSilentAndDoesNotWalk()
    {
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(function (string $from): bool {
            FileSystemUtils::removeDirRecursive($from);

            return false;
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertCount(1, $cleaner->renames);
        $this->assertSame([], $cleaner->walkedDirs);
        $this->assertSame([], $logger->lines);
    }

    public function testWriterRecreatingTargetAfterRenameIsLeftUntouched()
    {
        $fresh = $this->targetDir . '/fresh.body';
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(function (string $from, string $to) use ($fresh): bool {
            rename($from, $to);
            $this->buildTree([$fresh]);

            return true;
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertStringEqualsFile($fresh, 'content');
        $this->assertFileDoesNotExist($this->targetDir . '/entry.body');
        $this->assertTrashIsEmpty();
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testRenameFailureLogsReasonAndDeletesInPlace()
    {
        $this->buildTree([$this->targetDir . '/entry.body', $this->targetDir . '/nested/deep.body']);
        $cleaner = $this->seamedCleaner(
            fn (string $from): bool => @rename($from, $this->testDir . '/missing/entry')
        );
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame([$this->targetDir], $cleaner->walkedDirs);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertTrashIsEmpty();
        $this->assertCount(2, $logger->lines);
        [$level, $message] = $logger->lines[0];
        $this->assertSame('warn', $level);
        $this->assertStringStartsWith(
            'cache trash rename failed — dir: ' . $this->targetDir . ', reason: rename(',
            $message
        );
        $this->assertStringContainsString('No such file or directory', $message);
        $this->assertSame($this->clearedLine(), $logger->lines[1]);
    }

    public function testRenameFailureWithoutErrorMessageStillFallsBack()
    {
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(fn (): bool => false);
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([
            ['warn', 'cache trash rename failed — dir: ' . $this->targetDir . ', reason: unknown error'],
            $this->clearedLine()
        ], $logger->lines);
    }

    public function testRenameThrowingIsNotPropagated()
    {
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(function (): bool {
            throw new \RuntimeException('rename exploded');
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([
            ['warn', 'cache trash rename failed — dir: ' . $this->targetDir . ', reason: rename exploded'],
            $this->clearedLine()
        ], $logger->lines);
    }

    public function testNoDebugLineWhenRootVanishesDuringInPlaceDeletion()
    {
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner(
            fn (): bool => false,
            null,
            function (string $dir): void {
                FileSystemUtils::removeDirRecursive($dir);
                throw new \UnexpectedValueException('root vanished');
            }
        );
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertCount(1, $logger->lines);
        $this->assertSame('warn', $logger->lines[0][0]);
    }

    public function testTrashOccupiedByRegularFileFallsBackAndKeepsTheFile()
    {
        file_put_contents($this->trashDir, 'not a directory');
        $this->buildTree([$this->targetDir . '/entry.body']);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame([], $cleaner->renames);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertStringEqualsFile($this->trashDir, 'not a directory');
        $this->assertSame([$this->unusableTrashLine(), $this->clearedLine()], $logger->lines);
    }

    public function testSymlinkedTrashFallsBackAndLeavesLinkTargetUntouched()
    {
        $oldEntry = $this->outsideDir . '/1.0123456789abcdef/entry.body';
        $this->buildTree([$this->outsideDir . '/precious.txt', $oldEntry, $this->targetDir . '/entry.body']);
        symlink($this->outsideDir, $this->trashDir);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame([], $cleaner->renames);
        $this->assertSame([$this->targetDir], $cleaner->walkedDirs);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertTrue(is_link($this->trashDir));
        $this->assertSame(
            ['.', '..', '1.0123456789abcdef', 'precious.txt'],
            scandir($this->outsideDir)
        );
        $this->assertStringEqualsFile($this->outsideDir . '/precious.txt', 'content');
        $this->assertStringEqualsFile($oldEntry, 'content');
        $this->assertSame([$this->unusableTrashLine(), $this->clearedLine()], $logger->lines);
    }

    public function testUnusableTrashIsSilentWhenTargetDoesNotExist()
    {
        file_put_contents($this->trashDir, 'not a directory');
        $logger = $this->captureLogs();

        $this->seamedCleaner()->clean('collection', '/users');

        $this->assertSame([], $logger->lines);
    }

    public function testMissingCacheLocationIsSilentAndNotCreated()
    {
        $missing = $this->testDir . '/missing';
        $cleaner = new CacheDirCleaner(new FolderLocation($missing));
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($missing);
        $this->assertSame([], $logger->lines);
    }

    /**
     * @dataProvider reservedPathProvider
     */
    public function testReservedCustomTargetIsIgnored(string $path)
    {
        $planted = $this->cacheDir . '/' . trim($path, '/') . '/GET/entry.body';
        mkdir($this->cacheDir . '/users');
        $this->buildTree([$planted]);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->cleanPath($path);

        $this->assertStringEqualsFile($planted, 'content');
        $this->assertSame([], $cleaner->renames);
        $this->assertSame([], $cleaner->walkedDirs);
        $this->assertSame([], $logger->lines);
    }

    public function reservedPathProvider(): array
    {
        return [
            'entry' => ['/.tent-trash/x'],
            'trash itself' => ['/.tent-trash'],
            'upper case' => ['/.TENT-TRASH/x'],
        ];
    }

    /**
     * @dataProvider unsafeReservedPathProvider
     */
    public function testReservedCustomTargetWithDotSegmentsIsRefusedAsUnsafe(string $path)
    {
        $planted = $this->cacheDir . '/' . trim($path, '/') . '/GET/entry.body';
        mkdir($this->cacheDir . '/users');
        $this->buildTree([$planted]);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->cleanPath($path);

        $this->assertStringEqualsFile($planted, 'content');
        $this->assertSame([], $cleaner->renames);
        $this->assertSame([], $cleaner->walkedDirs);
        $this->assertSame(
            [['warn', 'cache cleanup refused — path: ' . $path . ', reason: unsafe path']],
            $logger->lines
        );
    }

    public function unsafeReservedPathProvider(): array
    {
        return [
            'dot segment' => ['/./.tent-trash/x'],
            'parent segment' => ['/users/../.tent-trash/x'],
        ];
    }

    /**
     * @dataProvider reservedTargetProvider
     */
    public function testReservedCollectionAndEntityTargetsAreIgnored(string $target, string $resolved)
    {
        $planted = $this->cacheDir . $resolved . '/entry.body';
        $this->buildTree([$planted]);
        $cleaner = $this->seamedCleaner();
        $logger = $this->captureLogs();

        $cleaner->clean($target, '/.tent-trash/x');

        $this->assertStringEqualsFile($planted, 'content');
        $this->assertSame([], $cleaner->renames);
        $this->assertSame([], $logger->lines);
    }

    public function reservedTargetProvider(): array
    {
        return [
            'collection' => ['collection', '/.tent-trash/GET'],
            'entity' => ['entity', '/.tent-trash/x/GET'],
        ];
    }

    public function testDeeperTrashNameIsCleanedNormally()
    {
        $dir = $this->cacheDir . '/users/.tent-trash/GET';
        $this->buildTree([$dir . '/entry.body']);

        $this->seamedCleaner()->cleanPath('/users/.tent-trash');

        $this->assertDirectoryDoesNotExist($dir);
    }

    /**
     * Builds a cleaner recording its renames and walks.
     *
     * `$onRename` replaces the rename, `$time` freezes the clock and `$onWalk`
     * is called before each walk attempt.
     */
    private function seamedCleaner(
        ?\Closure $onRename = null,
        ?int $time = null,
        ?\Closure $onWalk = null
    ): CacheDirCleaner {
        $location = new FolderLocation($this->cacheDir);

        return new class ($location, $onRename, $time, $onWalk) extends CacheDirCleaner {
            public array $renames = [];
            public array $walkedDirs = [];

            public function __construct(
                FolderLocation $location,
                private ?\Closure $onRename,
                private ?int $time,
                private ?\Closure $onWalk
            ) {
                parent::__construct($location);
            }

            protected function renameDir(string $from, string $to): bool
            {
                $this->renames[] = [$from, $to];

                return $this->onRename ? ($this->onRename)($from, $to) : parent::renameDir($from, $to);
            }

            protected function now(): int
            {
                return $this->time ?? parent::now();
            }

            protected function createIterator(string $dir): \Iterator
            {
                $this->walkedDirs[] = $dir;
                if ($this->onWalk) {
                    ($this->onWalk)($dir);
                }

                return parent::createIterator($dir);
            }
        };
    }

    private function buildTree(array $files): void
    {
        foreach ($files as $file) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }
            file_put_contents($file, 'content');
        }
    }

    private function captureLogs(): CapturingLoggerInstance
    {
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        return $logger;
    }

    private function clearedLine(): array
    {
        return ['debug', 'cache cleared — dir: ' . $this->targetDir];
    }

    private function unusableTrashLine(): array
    {
        return ['warn', 'cache trash is not a real directory — path: ' . $this->trashDir];
    }

    private function assertTrashIsEmpty(): void
    {
        $this->assertDirectoryExists($this->trashDir);
        $this->assertSame([], array_values(array_diff(scandir($this->trashDir), ['.', '..'])));
    }
}
