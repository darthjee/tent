<?php

namespace Tent\Tests\Log;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\LoggerInstance;

class LoggerInstanceTest extends TestCase
{
    private string $originalLogLevel;

    protected function setUp(): void
    {
        $this->originalLogLevel = (string) getenv('LOG_LEVEL');
    }

    protected function tearDown(): void
    {
        if ($this->originalLogLevel !== '') {
            putenv('LOG_LEVEL=' . $this->originalLogLevel);
        } else {
            putenv('LOG_LEVEL');
        }
    }

    public function testLogWritesDebugWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[DEBUG\] hello/');
        $instance->log('hello', 'debug');
    }

    public function testLogWritesInfoWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[INFO\] message/');
        $instance->log('message', 'info');
    }

    public function testLogWritesWarnWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[WARN\] message/');
        $instance->log('message', 'warn');
    }

    public function testLogWritesErrorWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[ERROR\] message/');
        $instance->log('message', 'error');
    }

    public function testLogSuppressesDebugWhenThresholdIsInfo(): void
    {
        putenv('LOG_LEVEL=info');
        $instance = new LoggerInstance();

        $this->expectOutputString('');
        $instance->log('silent', 'debug');
    }

    public function testLogWritesInfoWhenThresholdIsInfo(): void
    {
        putenv('LOG_LEVEL=info');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[INFO\] visible/');
        $instance->log('visible', 'info');
    }

    public function testLogSuppressesDebugAndInfoWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $this->expectOutputString('');
        $instance->log('debug msg', 'debug');
        $instance->log('info msg', 'info');
    }

    public function testLogWritesWarnWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[WARN\] visible/');
        $instance->log('visible', 'warn');
    }

    public function testLogWritesErrorWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[ERROR\] visible/');
        $instance->log('visible', 'error');
    }

    public function testLogOnlyWritesErrorWhenThresholdIsError(): void
    {
        putenv('LOG_LEVEL=error');
        $instance = new LoggerInstance();

        $this->expectOutputString('');
        $instance->log('debug msg', 'debug');
        $instance->log('info msg', 'info');
        $instance->log('warn msg', 'warn');
    }

    public function testLogWritesErrorWhenThresholdIsError(): void
    {
        putenv('LOG_LEVEL=error');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[ERROR\] visible/');
        $instance->log('visible', 'error');
    }

    public function testDisableSuppressesAllOutput(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();
        $instance->disable();

        $this->expectOutputString('');
        $instance->log('debug msg', 'debug');
        $instance->log('error msg', 'error');
    }

    public function testEnableRestoresOutputAfterDisable(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();
        $instance->disable();
        $instance->enable();

        $this->expectOutputRegex('/\[INFO\] restored/');
        $instance->log('restored', 'info');
    }

    public function testDefaultThresholdIsDebugWhenEnvNotSet(): void
    {
        putenv('LOG_LEVEL');
        $instance = new LoggerInstance();

        $this->expectOutputRegex('/\[DEBUG\] everything/');
        $instance->log('everything', 'debug');
    }
}
