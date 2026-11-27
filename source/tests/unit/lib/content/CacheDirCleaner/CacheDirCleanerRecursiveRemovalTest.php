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
use Tent\Utils\RemovalResult;
use Tent\Utils\SafeRemover;

class CacheDirCleanerRecursiveRemovalTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cache_dir_cleaner_removal_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
        $this->targetDir = $this->cacheDir . '/users/GET';
        mkdir($this->cacheDir, 0777, true);
        mkdir($this->outsideDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testDeletingDirThatNoLongerExistsIsSilentNoOp()
    {
        $logger = $this->captureLogs();
        $cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));

        $cleaner->clean('collection', '/users');
        $cleaner->cleanPath('/users');

        $this->assertSame([], $logger->lines);
        $this->assertDirectoryDoesNotExist($this->targetDir);
    }

    public function testToleratesFileRemovedBetweenIterationAndUnlink()
    {
        $racedFile = $this->targetDir . '/raced.body';
        $this->buildTree([$racedFile, $this->targetDir . '/sibling.body', $this->targetDir . '/nested/other.body']);
        $remover = new class ($racedFile) extends SafeRemover {
            public int $races = 0;

            public function __construct(private string $racedFile)
            {
            }

            public function removeFile(string $path): RemovalResult
            {
                if ($path === $this->racedFile) {
                    unlink($path);
                    $this->races++;
                }

                return parent::removeFile($path);
            }
        };
        $logger = $this->captureLogs();
        $cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir), $remover);

        $cleaner->clean('collection', '/users');

        $this->assertSame(1, $remover->races);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testToleratesSubdirectoryRemovedMidWalk()
    {
        $subDir = $this->targetDir . '/nested';
        $this->buildTree([$subDir . '/entry.body', $this->targetDir . '/sibling.body']);
        $cleaner = $this->racingCleaner(function (int $attempt) use ($subDir) {
            if ($attempt === 1) {
                FileSystemUtils::removeDirRecursive($subDir);
                throw new \UnexpectedValueException('subdirectory vanished');
            }
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testSymlinkInsideTreeIsRemovedAndTargetUntouched()
    {
        $target = $this->outsideDir . '/target.body';
        $link = $this->targetDir . '/link.body';
        $this->buildTree([$target]);
        mkdir($this->targetDir, 0777, true);
        symlink($target, $link);
        $cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));

        $cleaner->clean('collection', '/users');

        $this->assertFalse(is_link($link));
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertStringEqualsFile($target, 'content');
    }

    public function testRefusesPathOutsideCacheLocation()
    {
        $this->buildTree([$this->outsideDir . '/GET/entry.body']);
        $escapingLocation = new FolderLocation($this->cacheDir . '/users/../..');
        $logger = $this->captureLogs();
        $cleaner = new CacheDirCleaner($escapingLocation);

        $cleaner->clean('collection', 'outside');
        $cleaner->cleanPath('outside');

        $this->assertFileExists($this->outsideDir . '/GET/entry.body');
        $this->assertSame([], $logger->lines);
    }

    public function testRestartsWalkAndRemovesUnreachedSiblingsAfterMidWalkRace()
    {
        $visited = $this->targetDir . '/visited.body';
        $this->buildTree([$visited, $this->targetDir . '/unreached.body', $this->targetDir . '/nested/deep.body']);
        $interruptedWalk = function () use ($visited): \Generator {
            yield new \SplFileInfo($visited);
            throw new \UnexpectedValueException('walk interrupted');
        };
        $cleaner = $this->racingCleaner(
            fn (int $attempt): ?\Iterator => $attempt === 1 ? $interruptedWalk() : null
        );
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame(2, $cleaner->attempts);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testLogsSingleWarningWhenWalkFailsOnEveryAttempt()
    {
        $file = $this->targetDir . '/entry.body';
        $this->buildTree([$file]);
        $cleaner = $this->racingCleaner(function () {
            throw new \UnexpectedValueException('permission denied');
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame(3, $cleaner->attempts);
        $this->assertFileExists($file);
        $this->assertSingleRootWarning($logger);
    }

    public function testResolvedPathThatIsAFileIsLeftUntouched()
    {
        $this->buildTree([$this->targetDir]);
        $logger = $this->captureLogs();
        $cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));

        $cleaner->clean('collection', '/users');

        $this->assertStringEqualsFile($this->targetDir, 'content');
        $this->assertSame([], $logger->lines);
    }

    public function testSymlinkedRootIsUnlinkedAndTargetUntouched()
    {
        $this->buildTree([$this->outsideDir . '/entry.body', $this->outsideDir . '/nested/deep.body']);
        mkdir(dirname($this->targetDir), 0777, true);
        symlink($this->outsideDir, $this->targetDir);
        $logger = $this->captureLogs();
        $cleaner = new CacheDirCleaner(new FolderLocation($this->cacheDir));

        $cleaner->clean('collection', '/users');

        $this->assertFalse(is_link($this->targetDir));
        $this->assertStringEqualsFile($this->outsideDir . '/entry.body', 'content');
        $this->assertStringEqualsFile($this->outsideDir . '/nested/deep.body', 'content');
        $this->assertSame([$this->clearedLine()], $logger->lines);
    }

    public function testNoDebugLogWhenRootRemovedByConcurrentWriterMidWalk()
    {
        $this->buildTree([$this->targetDir . '/nested/entry.body']);
        $cleaner = $this->racingCleaner(function (int $attempt, string $dir) {
            FileSystemUtils::removeDirRecursive($dir);
            throw new \UnexpectedValueException('root vanished');
        });
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame(1, $cleaner->attempts);
        $this->assertDirectoryDoesNotExist($this->targetDir);
        $this->assertSame([], $logger->lines);
    }

    public function testRealFileFailureLeavesRootAndLogsOnlyHelperWarnings()
    {
        $stuckFile = $this->targetDir . '/stuck.body';
        $sibling = $this->targetDir . '/sibling.body';
        $this->buildTree([$stuckFile, $sibling]);
        $remover = new class ($stuckFile) extends SafeRemover {
            public function __construct(private string $stuckFile)
            {
            }

            public function removeFile(string $path): RemovalResult
            {
                if ($path === $this->stuckFile) {
                    return RemovalResult::Failed;
                }

                return parent::removeFile($path);
            }
        };
        $cleaner = $this->racingCleaner(fn (): ?\Iterator => null, $remover);
        $logger = $this->captureLogs();

        $cleaner->clean('collection', '/users');

        $this->assertSame(1, $cleaner->attempts);
        $this->assertFileExists($stuckFile);
        $this->assertFileDoesNotExist($sibling);
        $this->assertSingleRootWarning($logger);
    }

    /**
     * Builds a cleaner whose walk can be disturbed before each attempt.
     *
     * The callback receives the attempt number and the directory. It may throw,
     * return a replacement iterator, or return null to use the real one.
     */
    private function racingCleaner(\Closure $onAttempt, ?SafeRemover $remover = null): CacheDirCleaner
    {
        $location = new FolderLocation($this->cacheDir);

        return new class ($location, $onAttempt, $remover) extends CacheDirCleaner {
            public int $attempts = 0;

            public function __construct(
                FolderLocation $location,
                private \Closure $onAttempt,
                ?SafeRemover $remover
            ) {
                parent::__construct($location, $remover);
            }

            protected function createIterator(string $dir): \Iterator
            {
                $this->attempts++;

                return ($this->onAttempt)($this->attempts, $dir) ?? parent::createIterator($dir);
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

    private function assertSingleRootWarning(CapturingLoggerInstance $logger): void
    {
        $this->assertCount(1, $logger->lines);
        [$level, $message] = $logger->lines[0];
        $this->assertSame('warn', $level);
        $this->assertStringStartsWith('cache removal failed — dir: ' . $this->targetDir . ' — ', $message);
    }
}
