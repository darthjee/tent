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

class SafeRemoverRemoveDirTest extends TestCase
{
    private string $testDir;
    private SafeRemover $remover;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/safe_remover_dir_test_' . uniqid();
        mkdir($this->testDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->remover = new SafeRemover();
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRemoveDirDeletesEmptyDir()
    {
        $directory = $this->testDir . '/empty';
        mkdir($directory);
        $this->expectNoLog();

        $result = $this->remover->removeDir($directory);

        $this->assertSame(RemovalResult::Removed, $result);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function testRemoveDirIsSilentWhenDirAlreadyGone()
    {
        $this->expectNoLog();

        $result = $this->remover->removeDir($this->testDir . '/missing');

        $this->assertSame(RemovalResult::AlreadyGone, $result);
    }

    public function testRemoveDirLogsWarningWhenDirIsNotEmpty()
    {
        $directory = $this->testDir . '/not_empty';
        mkdir($directory);
        file_put_contents($directory . '/entry.body', 'content');

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with(
                $this->logicalAnd(
                    $this->stringStartsWith('cache removal failed — dir: '),
                    $this->stringContains($directory)
                ),
                'warn'
            );
        Logger::setInstance($instance);

        $result = $this->remover->removeDir($directory);

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
