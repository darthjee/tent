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

class CacheDirCleanerOrphanSweepTest extends TestCase
{
    private const NOW = 1700000000;
    private const OLD = '1699990000.0000000000000001';
    private const OTHER_OLD = '1699990001.0000000000000002';

    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;
    private string $trashDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cache_dir_cleaner_sweep_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
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

    public function testSweepsOrphanOlderThanTheGracePeriod()
    {
        $this->plantEntry(self::OLD);
        $logger = $this->captureLogs();

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([], $this->trashEntries());
        $this->assertSame([$this->sweptLine(self::OLD)], $logger->lines);
    }

    public function testSweepsOrphanExactlyAsOldAsTheGracePeriod()
    {
        $this->plantEntry((self::NOW - 600) . '.0000000000000001');

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([], $this->trashEntries());
    }

    /**
     * @dataProvider youngTimestampProvider
     */
    public function testLeavesYoungEntriesAlone(int $timestamp)
    {
        $name = $timestamp . '.0000000000000001';
        $this->plantEntry($name);
        $logger = $this->captureLogs();

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([$name], $this->trashEntries());
        $this->assertFileExists($this->trashDir . '/' . $name . '/nested/deep.body');
        $this->assertSame([], $logger->lines);
    }

    public function youngTimestampProvider(): array
    {
        return [
            'just inside the grace period' => [self::NOW - 599],
            'just trashed' => [self::NOW],
            'ahead of the clock' => [self::NOW + 3600],
        ];
    }

    public function testSweepsOnlyOneOrphanPerCall()
    {
        $this->plantEntry(self::OLD);
        $this->plantEntry(self::OTHER_OLD);
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner();

        $cleaner->cleanPath('/users');

        $this->assertSame([self::OTHER_OLD], $this->trashEntries());
        $this->assertSame([$this->sweptLine(self::OLD)], $logger->lines);

        $cleaner->clean('collection', '/users');

        $this->assertSame([], $this->trashEntries());
        $this->assertSame(
            [$this->sweptLine(self::OLD), $this->sweptLine(self::OTHER_OLD)],
            $logger->lines
        );
    }

    public function testClaimsOrphanUnderAFreshEntryName()
    {
        $this->plantEntry(self::OLD);
        $cleaner = $this->cleaner();

        $cleaner->clean('entity', '/users');

        $this->assertCount(1, $cleaner->renames);
        [$from, $to] = $cleaner->renames[0];
        $this->assertSame($this->trashDir . '/' . self::OLD, $from);
        $this->assertMatchesRegularExpression(
            '#^' . preg_quote($this->trashDir . '/' . self::NOW . '.', '#') . '[0-9a-f]{16}$#',
            $to
        );
    }

    public function testOrphanClaimedByAnotherSweeperIsSilent()
    {
        $this->plantEntry(self::OLD);
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner(function (string $from): bool {
            FileSystemUtils::removeDirRecursive($from);

            return false;
        });

        $cleaner->clean('entity', '/users');

        $this->assertCount(1, $cleaner->renames);
        $this->assertSame([], $this->trashEntries());
        $this->assertSame([], $logger->lines);
    }

    public function testTriesNextOrphanAfterLosingAClaim()
    {
        $third = '1699990002.0000000000000003';
        $this->plantEntry(self::OLD);
        $this->plantEntry(self::OTHER_OLD);
        $this->plantEntry($third);
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner(function (string $from, string $to): bool {
            if (basename($from) === self::OLD) {
                FileSystemUtils::removeDirRecursive($from);

                return false;
            }

            return rename($from, $to);
        });

        $cleaner->clean('entity', '/users');

        $this->assertCount(2, $cleaner->renames);
        $this->assertSame([$third], $this->trashEntries());
        $this->assertSame([$this->sweptLine(self::OTHER_OLD)], $logger->lines);
    }

    public function testClaimFailureIsLoggedAndStopsTheSweep()
    {
        $this->plantEntry(self::OLD);
        $this->plantEntry(self::OTHER_OLD);
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner(fn (): bool => false);

        $cleaner->clean('entity', '/users');

        $this->assertCount(1, $cleaner->renames);
        $this->assertSame([self::OLD, self::OTHER_OLD], $this->trashEntries());
        $this->assertSame([[
            'warn',
            'cache trash orphan claim failed — entry: ' . self::OLD . ', reason: unknown error'
        ]], $logger->lines);
    }

    public function testLeavesForeignNamesAlone()
    {
        $this->plantEntry('x');
        $this->plantEntry('1.not-an-entry');
        file_put_contents($this->trashDir . '/notes.txt', 'content');
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner();

        $cleaner->cleanPath('/users');

        $this->assertSame(['1.not-an-entry', 'notes.txt', 'x'], $this->trashEntries());
        $this->assertSame([], $logger->lines);
    }

    public function testUnlinksTopLevelLinkWithoutFollowingIt()
    {
        mkdir($this->trashDir);
        file_put_contents($this->outsideDir . '/precious.txt', 'content');
        symlink($this->outsideDir, $this->trashDir . '/' . self::OLD);
        $logger = $this->captureLogs();

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([], $this->trashEntries());
        $this->assertStringEqualsFile($this->outsideDir . '/precious.txt', 'content');
        $this->assertSame([$this->sweptLine(self::OLD)], $logger->lines);
    }

    public function testDoesNotFollowLinksNestedInAnOrphan()
    {
        $this->plantEntry(self::OLD);
        file_put_contents($this->outsideDir . '/precious.txt', 'content');
        symlink($this->outsideDir, $this->trashDir . '/' . self::OLD . '/link');

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([], $this->trashEntries());
        $this->assertStringEqualsFile($this->outsideDir . '/precious.txt', 'content');
    }

    public function testRemovesOrphanWhichIsAPlainFile()
    {
        mkdir($this->trashDir);
        file_put_contents($this->trashDir . '/' . self::OLD, 'content');
        $logger = $this->captureLogs();

        $this->cleaner()->cleanPath('/users');

        $this->assertSame([], $this->trashEntries());
        $this->assertSame([$this->sweptLine(self::OLD)], $logger->lines);
    }

    public function testDoesNotSweepThroughASymlinkedTrash()
    {
        $orphan = $this->outsideDir . '/' . self::OLD . '/entry.body';
        mkdir(dirname($orphan));
        file_put_contents($orphan, 'content');
        symlink($this->outsideDir, $this->trashDir);
        $logger = $this->captureLogs();
        $cleaner = $this->cleaner();

        $cleaner->cleanPath('/users');
        $cleaner->clean('entity', '/users');

        $this->assertSame([], $cleaner->renames);
        $this->assertStringEqualsFile($orphan, 'content');
        $this->assertSame([], $logger->lines);
    }

    public function testSweepDoesNotCreateTheTrash()
    {
        $this->cleaner()->clean('entity', '/users');

        $this->assertDirectoryDoesNotExist($this->trashDir);
    }

    public function testSweepsAfterDeletingTheTarget()
    {
        $this->plantEntry(self::OLD);
        $target = $this->cacheDir . '/users/GET';
        mkdir($target, 0777, true);
        $logger = $this->captureLogs();

        $this->cleaner()->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($target);
        $this->assertSame([], $this->trashEntries());
        $this->assertSame([
            ['debug', 'cache cleared — dir: ' . $target],
            $this->sweptLine(self::OLD)
        ], $logger->lines);
    }

    public function testSweepsAfterTheInPlaceFallback()
    {
        $this->plantEntry(self::OLD);
        $target = $this->cacheDir . '/users/GET';
        mkdir($target, 0777, true);
        $cleaner = $this->cleaner(
            fn (string $from, string $to): bool => $from !== $target && rename($from, $to)
        );

        $cleaner->clean('collection', '/users');

        $this->assertDirectoryDoesNotExist($target);
        $this->assertSame([], $this->trashEntries());
    }

    /**
     * @dataProvider noOpTargetProvider
     */
    public function testSweepsWhenTheCallHasNothingToDelete(string $path)
    {
        $this->plantEntry(self::OLD);

        $this->cleaner()->cleanPath($path);

        $this->assertSame([], $this->trashEntries());
    }

    public function noOpTargetProvider(): array
    {
        return [
            'target already gone' => ['/users'],
            'reserved target' => ['/.tent-trash/x'],
        ];
    }

    public function testKeepsTheTrashFolder()
    {
        $this->plantEntry(self::OLD);

        $this->cleaner()->cleanPath('/users');

        $this->assertDirectoryExists($this->trashDir);
    }

    /**
     * Builds a cleaner with a frozen clock, recording its renames.
     *
     * `$onClaim` replaces the renames of entries already inside the trash and of
     * any other directory.
     */
    private function cleaner(?\Closure $onClaim = null): CacheDirCleaner
    {
        $location = new FolderLocation($this->cacheDir);

        return new class ($location, self::NOW, $onClaim) extends CacheDirCleaner {
            public array $renames = [];

            public function __construct(
                FolderLocation $location,
                private int $time,
                private ?\Closure $onClaim
            ) {
                parent::__construct($location);
            }

            protected function renameDir(string $from, string $to): bool
            {
                $this->renames[] = [$from, $to];

                return $this->onClaim ? ($this->onClaim)($from, $to) : parent::renameDir($from, $to);
            }

            protected function now(): int
            {
                return $this->time;
            }
        };
    }

    private function plantEntry(string $name): void
    {
        $dir = $this->trashDir . '/' . $name . '/nested';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/deep.body', 'content');
    }

    private function trashEntries(): array
    {
        return array_values(array_diff(scandir($this->trashDir), ['.', '..']));
    }

    private function captureLogs(): CapturingLoggerInstance
    {
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        return $logger;
    }

    private function sweptLine(string $name): array
    {
        return ['debug', 'cache trash orphan swept — entry: ' . $name];
    }
}
