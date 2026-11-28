<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Tests\Support\Utils\PhpWarningCatcher;

class FileCacheMiddlewareOverlongPathTest extends TestCase
{
    private string $cacheDir;

    private FileCacheMiddleware $middleware;

    private CapturingLoggerInstance $logger;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_overlong_test_' . uniqid();
        mkdir($this->cacheDir, 0777, true);
        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);
        $this->middleware = new FileCacheMiddleware(new FolderLocation($this->cacheDir), evictOn: ['4xx']);

        $this->catcher = new PhpWarningCatcher();
        $this->catcher->start();
    }

    protected function assertPostConditions(): void
    {
        $this->assertSame([], $this->catcher->warnings());
    }

    protected function tearDown(): void
    {
        $this->catcher->stop();
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testProcessRequestIsAMiss()
    {
        $request = $this->buildRequest();

        $result = $this->middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertFalse($result->hasResponse());
    }

    public function testWholeRequestLogsASingleWarning()
    {
        $request = $this->buildRequest();

        $this->middleware->processRequest($request);
        $this->middleware->processResponse(
            new Response(['body' => 'upstream body', 'httpCode' => 200, 'request' => $request])
        );

        $this->assertCount(1, $this->logger->lines);
        $this->assertSame('warn', $this->logger->lines[0][0]);
        $this->assertStringEndsWith(', reason: path too long', $this->logger->lines[0][1]);
    }

    /**
     * @dataProvider httpCodeProvider
     */
    public function testProcessResponseReturnsTheResponseUnchangedAndWritesNothing(int $httpCode)
    {
        $headers = ['Content-Type: text/plain', 'X-Custom: value'];
        $response = new Response([
            'body' => 'upstream body',
            'httpCode' => $httpCode,
            'headers' => $headers,
            'request' => $this->buildRequest()
        ]);

        $result = $this->middleware->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertSame($httpCode, $result->httpCode());
        $this->assertSame('upstream body', $result->body());
        $this->assertSame($headers, $result->headers());
        $this->assertSame(['.', '..'], scandir($this->cacheDir));
    }

    public function httpCodeProvider(): array
    {
        return [
            'cacheable response' => [200],
            'evicting response' => [404],
        ];
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath' => '/' . str_repeat('s', 300),
            'requestMethod' => 'GET'
        ]);
    }
}
