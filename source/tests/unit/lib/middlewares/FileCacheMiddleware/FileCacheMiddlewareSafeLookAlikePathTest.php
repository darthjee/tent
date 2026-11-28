<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareSafeLookAlikePathTest extends TestCase
{
    private string $cacheDir;
    private FileCacheMiddleware $middleware;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_lookalike_test_' . uniqid();
        mkdir($this->cacheDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());
        $this->middleware = new FileCacheMiddleware(new FolderLocation($this->cacheDir));
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider safeLookAlikePathProvider
     */
    public function testSecondRequestIsServedFromCache(string $path)
    {
        $first = $this->middleware->processRequest($this->buildRequest($path));
        $this->assertFalse($first->hasResponse());

        $this->middleware->processResponse(new Response([
            'body' => 'upstream body',
            'httpCode' => 201,
            'headers' => ['Content-Type: text/plain'],
            'request' => $first
        ]));

        $second = $this->middleware->processRequest($this->buildRequest($path));

        $this->assertTrue($second->hasResponse());
        $this->assertSame('upstream body', $second->response()->body());
        $this->assertSame(201, $second->response()->httpCode());
        $this->assertContains('Content-Type: text/plain', $second->response()->headers());
    }

    public function safeLookAlikePathProvider(): array
    {
        return [
            'three dots' => ['/...'],
            'leading double dot' => ['/..foo'],
            'leading double dot directory' => ['/..foo/x'],
            'trailing double dot' => ['/foo../x'],
            'inner double dot' => ['/a..b'],
            'backslash' => ['/a\\b'],
            'backslash around double dot' => ['/a\\..\\b'],
        ];
    }

    private function buildRequest(string $path): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => $path, 'requestMethod' => 'GET']);
    }
}
