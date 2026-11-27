<?php

namespace Tent\Tests\Utils\SafeRemover;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Utils\RemovalResult;
use Tent\Utils\SafeRemover;

class SafeRemoverRemoveFileTest extends TestCase
{
    private string $testDir;
    private SafeRemover $remover;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/safe_remover_file_test_' . uniqid();
        mkdir($this->testDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->remover = new SafeRemover();
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRemoveFileDeletesExistingFile()
    {
        $file = $this->testDir . '/entry.body';
        file_put_contents($file, 'content');
        $this->expectNoLog();

        $result = $this->remover->removeFile($file);

        $this->assertSame(RemovalResult::Removed, $result);
        $this->assertFileDoesNotExist($file);
    }

    public function testRemoveFileIsSilentWhenFileAlreadyGone()
    {
        $this->expectNoLog();

        $result = $this->remover->removeFile($this->testDir . '/missing.body');

        $this->assertSame(RemovalResult::AlreadyGone, $result);
    }

    public function testRemoveFileLogsWarningWhenDeletionFails()
    {
        $directory = $this->testDir . '/not_a_file';
        mkdir($directory);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with(
                $this->logicalAnd(
                    $this->stringStartsWith('cache removal failed — file: '),
                    $this->stringContains($directory)
                ),
                'warn'
            );
        Logger::setInstance($instance);

        $result = $this->remover->removeFile($directory);

        $this->assertSame(RemovalResult::Failed, $result);
        $this->assertDirectoryExists($directory);
    }

    private function expectNoLog(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->never())->method('log');
        Logger::setInstance($instance);
    }
}
