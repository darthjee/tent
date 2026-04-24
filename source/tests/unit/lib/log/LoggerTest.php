<?php

namespace Tent\Tests\Log;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testSetInstanceReplacesActiveInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('msg', 'debug');

        Logger::setInstance($instance);
        Logger::debug('msg');
    }

    public function testDebugDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('hello', 'debug');

        Logger::setInstance($instance);
        Logger::debug('hello');
    }

    public function testInfoDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('hello', 'info');

        Logger::setInstance($instance);
        Logger::info('hello');
    }

    public function testWarnDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('hello', 'warn');

        Logger::setInstance($instance);
        Logger::warn('hello');
    }

    public function testErrorDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('hello', 'error');

        Logger::setInstance($instance);
        Logger::error('hello');
    }

    public function testEnableDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('enable');

        Logger::setInstance($instance);
        Logger::enable();
    }

    public function testDisableDelegatesToInstance(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('disable');

        Logger::setInstance($instance);
        Logger::disable();
    }
}
