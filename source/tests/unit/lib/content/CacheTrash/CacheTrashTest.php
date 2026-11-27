<?php

namespace Tent\Tests\Content\CacheTrash;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheTrash;
use Tent\Models\FolderLocation;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheTrashTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $trashDir;
    private CacheTrash $trash;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cache_trash_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->trashDir = $this->cacheDir . '/.tent-trash';
        mkdir($this->cacheDir, 0777, true);
        $this->trash = new CacheTrash(new FolderLocation($this->cacheDir));
    }

    protected function tearDown(): void
    {
        if (is_link($this->trashDir)) {
            unlink($this->trashDir);
        }
        FileSystemUtils::removeDirRecursive($this->testDir);
    }

    public function testPathIsInsideTheCacheLocation()
    {
        $this->assertSame($this->trashDir, $this->trash->path());
    }

    public function testPathIgnoresTrailingSlashOfTheLocation()
    {
        $trash = new CacheTrash(new FolderLocation($this->cacheDir . '/'));

        $this->assertSame($this->trashDir, $trash->path());
    }

    /**
     * @dataProvider reservedPathProvider
     */
    public function testContainsReservedPaths(string $path)
    {
        $this->assertTrue($this->trash->contains($this->cacheDir . $path));
    }

    public function reservedPathProvider(): array
    {
        return [
            'trash itself' => ['/.tent-trash'],
            'trash with trailing slash' => ['/.tent-trash/'],
            'entry' => ['/.tent-trash/x'],
            'nested entry' => ['/.tent-trash/x/GET'],
            'dot segment' => ['/./.tent-trash/x'],
            'double slash' => ['//.tent-trash'],
            'parent segment' => ['/users/../.tent-trash/x'],
            'leaving and re-entering the base' => ['/../cache/.tent-trash/x'],
            'upper case' => ['/.TENT-TRASH/x'],
            'mixed case' => ['/.Tent-Trash'],
        ];
    }

    /**
     * @dataProvider regularPathProvider
     */
    public function testDoesNotContainRegularPaths(string $path)
    {
        $this->assertFalse($this->trash->contains($this->cacheDir . $path));
    }

    public function regularPathProvider(): array
    {
        return [
            'cache root' => [''],
            'regular path' => ['/users/GET'],
            'deeper occurrence' => ['/users/.tent-trash'],
            'longer name' => ['/.tent-trash-old/GET'],
            'shorter name' => ['/.tent/GET'],
            'without the dot' => ['/tent-trash/GET'],
            'leaving the trash' => ['/.tent-trash/../users/GET'],
        ];
    }

    public function testContainsComparesRelativeLocationsLexically()
    {
        $trash = new CacheTrash(new FolderLocation('./cache'));

        $this->assertTrue($trash->contains('./cache/./.tent-trash//x/GET'));
        $this->assertTrue($trash->contains('cache/.tent-trash'));
        $this->assertFalse($trash->contains('./cache/users/GET'));
    }

    public function testEnsureCreatesMissingFolder()
    {
        $this->assertTrue($this->trash->ensure());
        $this->assertDirectoryExists($this->trashDir);
    }

    public function testEnsureAcceptsExistingFolderAndKeepsItsContent()
    {
        mkdir($this->trashDir);
        file_put_contents($this->trashDir . '/entry', 'content');

        $this->assertTrue($this->trash->ensure());
        $this->assertStringEqualsFile($this->trashDir . '/entry', 'content');
    }

    public function testEnsureDoesNotCreateTheCacheLocation()
    {
        $missing = $this->testDir . '/missing';
        $trash = new CacheTrash(new FolderLocation($missing));

        $this->assertFalse($trash->ensure());
        $this->assertDirectoryDoesNotExist($missing);
    }

    public function testEnsureRefusesRegularFileAndLeavesItUntouched()
    {
        file_put_contents($this->trashDir, 'content');

        $this->assertFalse($this->trash->ensure());
        $this->assertStringEqualsFile($this->trashDir, 'content');
    }

    public function testEnsureRefusesSymlinkToDirectoryAndLeavesItUntouched()
    {
        $outsideDir = $this->testDir . '/outside';
        mkdir($outsideDir);
        file_put_contents($outsideDir . '/file', 'content');
        symlink($outsideDir, $this->trashDir);

        $this->assertFalse($this->trash->ensure());
        $this->assertTrue(is_link($this->trashDir));
        $this->assertSame($outsideDir, readlink($this->trashDir));
        $this->assertStringEqualsFile($outsideDir . '/file', 'content');
    }

    public function testEnsureRefusesDanglingSymlink()
    {
        $missing = $this->testDir . '/missing';
        symlink($missing, $this->trashDir);

        $this->assertFalse($this->trash->ensure());
        $this->assertTrue(is_link($this->trashDir));
        $this->assertDirectoryDoesNotExist($missing);
    }

    public function testIsUsableDoesNotCreateTheFolder()
    {
        $this->assertFalse($this->trash->isUsable());
        $this->assertDirectoryDoesNotExist($this->trashDir);
    }

    public function testNewEntryNameHasTimestampAndRandomSuffix()
    {
        $name = $this->trash->newEntryName(1700000000);

        $this->assertMatchesRegularExpression('/^1700000000\.[0-9a-f]{16}$/', $name);
    }

    public function testNewEntryNamesAreUnique()
    {
        $this->assertNotSame($this->trash->newEntryName(1), $this->trash->newEntryName(1));
    }

    public function testEntryTimestampReadsGeneratedNames()
    {
        $name = $this->trash->newEntryName(1700000000);

        $this->assertSame(1700000000, $this->trash->entryTimestamp($name));
    }

    /**
     * @dataProvider foreignNameProvider
     */
    public function testEntryTimestampRejectsForeignNames(string $name)
    {
        $this->assertNull($this->trash->entryTimestamp($name));
    }

    public function foreignNameProvider(): array
    {
        return [
            'empty' => [''],
            'plain name' => ['users'],
            'timestamp only' => ['1700000000'],
            'missing suffix' => ['1700000000.'],
            'short suffix' => ['1700000000.0123456789abcde'],
            'long suffix' => ['1700000000.0123456789abcdef0'],
            'upper case suffix' => ['1700000000.0123456789ABCDEF'],
            'non hex suffix' => ['1700000000.0123456789abcdeg'],
            'missing timestamp' => ['.0123456789abcdef'],
            'negative timestamp' => ['-1.0123456789abcdef'],
            'trailing newline' => ["1700000000.0123456789abcdef\n"],
        ];
    }
}
