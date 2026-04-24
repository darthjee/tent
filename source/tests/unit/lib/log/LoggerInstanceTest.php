<?php

namespace Tent\Tests\Log;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\LoggerInstance;

class LoggerInstanceTest extends TestCase
{
    private string $originalLogLevel;
    private string $errorLogFile;
    private string $originalErrorLog;

    protected function setUp(): void
    {
        $this->originalLogLevel = (string) getenv('LOG_LEVEL');
        $this->errorLogFile = (string) tempnam(sys_get_temp_dir(), 'phpunit_log_');
        $this->originalErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        if ($this->originalLogLevel !== '') {
            putenv('LOG_LEVEL=' . $this->originalLogLevel);
        } else {
            putenv('LOG_LEVEL');
        }

        ini_set('error_log', $this->originalErrorLog);

        if (file_exists($this->errorLogFile)) {
            unlink($this->errorLogFile);
        }
    }

    private function getLogOutput(): string
    {
        return file_get_contents($this->errorLogFile) ?: '';
    }

    public function testLogWritesDebugWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $instance->log('hello', 'debug');

        $this->assertMatchesRegularExpression('/\[DEBUG\] hello/', $this->getLogOutput());
    }

    public function testLogWritesInfoWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $instance->log('message', 'info');

        $this->assertMatchesRegularExpression('/\[INFO\] message/', $this->getLogOutput());
    }

    public function testLogWritesWarnWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $instance->log('message', 'warn');

        $this->assertMatchesRegularExpression('/\[WARN\] message/', $this->getLogOutput());
    }

    public function testLogWritesErrorWhenThresholdIsDebug(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();

        $instance->log('message', 'error');

        $this->assertMatchesRegularExpression('/\[ERROR\] message/', $this->getLogOutput());
    }

    public function testLogSuppressesDebugWhenThresholdIsInfo(): void
    {
        putenv('LOG_LEVEL=info');
        $instance = new LoggerInstance();

        $instance->log('silent', 'debug');

        $this->assertEmpty($this->getLogOutput());
    }

    public function testLogWritesInfoWhenThresholdIsInfo(): void
    {
        putenv('LOG_LEVEL=info');
        $instance = new LoggerInstance();

        $instance->log('visible', 'info');

        $this->assertMatchesRegularExpression('/\[INFO\] visible/', $this->getLogOutput());
    }

    public function testLogSuppressesDebugAndInfoWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $instance->log('debug msg', 'debug');
        $instance->log('info msg', 'info');

        $this->assertEmpty($this->getLogOutput());
    }

    public function testLogWritesWarnWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $instance->log('visible', 'warn');

        $this->assertMatchesRegularExpression('/\[WARN\] visible/', $this->getLogOutput());
    }

    public function testLogWritesErrorWhenThresholdIsWarn(): void
    {
        putenv('LOG_LEVEL=warn');
        $instance = new LoggerInstance();

        $instance->log('visible', 'error');

        $this->assertMatchesRegularExpression('/\[ERROR\] visible/', $this->getLogOutput());
    }

    public function testLogOnlyWritesErrorWhenThresholdIsError(): void
    {
        putenv('LOG_LEVEL=error');
        $instance = new LoggerInstance();

        $instance->log('debug msg', 'debug');
        $instance->log('info msg', 'info');
        $instance->log('warn msg', 'warn');

        $this->assertEmpty($this->getLogOutput());
    }

    public function testLogWritesErrorWhenThresholdIsError(): void
    {
        putenv('LOG_LEVEL=error');
        $instance = new LoggerInstance();

        $instance->log('visible', 'error');

        $this->assertMatchesRegularExpression('/\[ERROR\] visible/', $this->getLogOutput());
    }

    public function testDisableSuppressesAllOutput(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();
        $instance->disable();

        $instance->log('debug msg', 'debug');
        $instance->log('error msg', 'error');

        $this->assertEmpty($this->getLogOutput());
    }

    public function testEnableRestoresOutputAfterDisable(): void
    {
        putenv('LOG_LEVEL=debug');
        $instance = new LoggerInstance();
        $instance->disable();
        $instance->enable();

        $instance->log('restored', 'info');

        $this->assertMatchesRegularExpression('/\[INFO\] restored/', $this->getLogOutput());
    }

    public function testDefaultThresholdIsDebugWhenEnvNotSet(): void
    {
        putenv('LOG_LEVEL');
        $instance = new LoggerInstance();

        $instance->log('everything', 'debug');

        $this->assertMatchesRegularExpression('/\[DEBUG\] everything/', $this->getLogOutput());
    }
}
