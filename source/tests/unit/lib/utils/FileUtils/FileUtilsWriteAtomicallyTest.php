<?php

namespace Tent\Tests\Utils\FileUtils;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Utils\FileUtils;

class FileUtilsWriteAtomicallyTest extends TestCase
{
    private string $testDir;
    private string $path;
    private int $umask;

    protected function setUp(): void
    {
        $this->umask = umask();
        $this->testDir = sys_get_temp_dir() . '/file_utils_write_atomically_test_' . uniqid();
        $this->path = $this->testDir . '/entry.body.dat';
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        umask($this->umask);
        chmod($this->testDir, 0755);
        FileSystemUtils::removeDirRecursive($this->testDir);
    }

    public function testWritesTheContent()
    {
        $this->assertTrue(FileUtils::writeAtomically($this->path, "some\0binary content"));

        $this->assertSame("some\0binary content", file_get_contents($this->path));
    }

    public function testWritesAnEmptyContent()
    {
        $this->assertTrue(FileUtils::writeAtomically($this->path, ''));

        $this->assertSame('', file_get_contents($this->path));
    }

    public function testOverwritesAnExistingFile()
    {
        file_put_contents($this->path, 'a much longer previous content');

        $this->assertTrue(FileUtils::writeAtomically($this->path, 'new content'));

        $this->assertSame('new content', file_get_contents($this->path));
    }

    public function testReplacesASymbolicLinkWithoutTouchingItsTarget()
    {
        $target = $this->testDir . '/target';
        file_put_contents($target, 'untouched');
        chmod($target, 0600);
        symlink($target, $this->path);

        $this->assertTrue(FileUtils::writeAtomically($this->path, 'new content'));

        clearstatcache();
        $this->assertFalse(is_link($this->path));
        $this->assertTrue(is_file($this->path));
        $this->assertSame('new content', file_get_contents($this->path));
        $this->assertSame('untouched', file_get_contents($target));
        $this->assertSame(0600, fileperms($target) & 0777);
    }

    public function testReplacesADanglingSymbolicLinkWithoutCreatingItsTarget()
    {
        $target = $this->testDir . '/missing';
        symlink($target, $this->path);

        $this->assertTrue(FileUtils::writeAtomically($this->path, 'new content'));

        clearstatcache();
        $this->assertFalse(is_link($this->path));
        $this->assertSame('new content', file_get_contents($this->path));
        $this->assertFileDoesNotExist($target);
    }

    /**
     * @dataProvider umaskProvider
     */
    public function testSetsTheFileModeWhateverTheUmask(int $umask)
    {
        umask($umask);

        $this->assertTrue(FileUtils::writeAtomically($this->path, 'content'));

        clearstatcache();
        $this->assertSame(0644, fileperms($this->path) & 0777);
    }

    public static function umaskProvider(): array
    {
        return [
            'permissive umask' => [0000],
            'default umask' => [0022],
            'restrictive umask' => [0077],
        ];
    }

    public function testResetsTheModeOfAnExistingFile()
    {
        file_put_contents($this->path, 'previous');
        chmod($this->path, 0666);

        $this->assertTrue(FileUtils::writeAtomically($this->path, 'content'));

        clearstatcache();
        $this->assertSame(0644, fileperms($this->path) & 0777);
    }

    public function testLeavesNoTemporaryFileAfterSuccess()
    {
        FileUtils::writeAtomically($this->path, 'content');

        $this->assertSame(['entry.body.dat'], $this->listTestDir());
    }

    public function testReturnsFalseWhenTheDirectoryDoesNotExist()
    {
        $path = $this->testDir . '/missing/entry.body.dat';

        $this->assertFalse(FileUtils::writeAtomically($path, 'content'));

        $this->assertDirectoryDoesNotExist($this->testDir . '/missing');
        $this->assertSame([], $this->listTestDir());
    }

    public function testReturnsFalseWhenTheDirectoryIsNotWritable()
    {
        chmod($this->testDir, 0555);

        if (is_writable($this->testDir)) {
            $this->markTestSkipped('Directory permissions are not enforced for this user');
        }

        $this->assertFalse(FileUtils::writeAtomically($this->path, 'content'));

        $this->assertSame([], $this->listTestDir());
    }

    public function testReturnsFalseAndRemovesTheTemporaryFileWhenTheRenameFails()
    {
        mkdir($this->path);

        $this->assertFalse(FileUtils::writeAtomically($this->path, 'content'));

        $this->assertDirectoryExists($this->path);
        $this->assertSame(['entry.body.dat'], $this->listTestDir());
    }

    public function testReturnsFalseWithoutThrowingForAPathWithANulByte()
    {
        $this->assertFalse(FileUtils::writeAtomically($this->testDir . "/entry\0", 'content'));

        $this->assertSame([], $this->listTestDir());
    }

    private function listTestDir(): array
    {
        return array_values(array_diff(scandir($this->testDir), ['.', '..']));
    }
}
