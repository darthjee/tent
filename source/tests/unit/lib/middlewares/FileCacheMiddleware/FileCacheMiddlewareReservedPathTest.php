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

class FileCacheMiddlewareReservedPathTest extends TestCase
{
    private string $cacheDir;
    private FileCacheMiddleware $middleware;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_reserved_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->middleware = new FileCacheMiddleware(new FolderLocation($this->cacheDir));
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testProcessRequestPassesReservedPathThrough()
    {
        $request = $this->buildRequest();

        $result = $this->middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertFalse($result->hasResponse());
    }

    public function testProcessResponseLeavesNothingInTheTrash()
    {
        $response = $this->buildResponse();

        $result = $this->middleware->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertSame(200, $result->httpCode());
        $this->assertSame('upstream body', $result->body());
        $this->assertDirectoryDoesNotExist($this->cacheDir . '/.tent-trash');
    }

    public function testReservedPathIsNeverServedFromCache()
    {
        $this->middleware->processResponse($this->buildResponse());

        $result = $this->middleware->processRequest($this->buildRequest());

        $this->assertFalse($result->hasResponse());
    }

    public function testExistingTrashFolderStaysEmpty()
    {
        mkdir($this->cacheDir . '/.tent-trash');

        $this->middleware->processResponse($this->buildResponse());

        $this->assertSame([], array_diff(scandir($this->cacheDir . '/.tent-trash'), ['.', '..']));
    }

    private function buildResponse(): Response
    {
        return new Response([
            'body' => 'upstream body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest()
        ]);
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => '/.tent-trash/x', 'requestMethod' => 'GET']);
    }
}
