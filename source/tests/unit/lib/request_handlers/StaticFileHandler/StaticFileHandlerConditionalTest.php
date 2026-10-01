<?php

namespace Tent\Tests\RequestHandlers\StaticFileHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\StaticFileHandler;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\Middleware;
use Tent\Models\FolderLocation;
use Tent\Models\ForbiddenResponse;
use Tent\Models\MissingResponse;
use Tent\Models\NotModifiedResponse;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class StaticFileHandlerConditionalTest extends TestCase
{
    private const MTIME = 1700000000;
    private const MTIME_DATE = 'Tue, 14 Nov 2023 22:13:20 GMT';

    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tent_test_' . uniqid();
        mkdir($this->testDir);
        file_put_contents($this->testDir . '/test.txt', 'Hello World');
        touch($this->testDir . '/test.txt', self::MTIME);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testOptionOffEmitsNoValidators()
    {
        $response = $this->handle(false, 'GET', '/test.txt');

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $response->headers());
    }

    public function testOptionOffIgnoresConditionalHeaders()
    {
        $response = $this->handle(false, 'GET', '/test.txt', ['If-None-Match' => $this->etag()]);

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertEquals(['Content-Type: text/plain', 'Content-Length: 11'], $response->headers());
    }

    public function testOptionOnAddsValidatorsTo200()
    {
        $response = $this->handle(true, 'GET', '/test.txt');

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertEquals(
            [
                'Content-Type: text/plain',
                'Content-Length: 11',
                'ETag: ' . $this->etag(),
                'Last-Modified: ' . self::MTIME_DATE
            ],
            $response->headers()
        );
    }

    public function testMatchingIfNoneMatchReturns304()
    {
        $response = $this->handle(true, 'GET', '/test.txt', ['If-None-Match' => $this->etag()]);

        $this->assertInstanceOf(NotModifiedResponse::class, $response);
        $this->assertEquals(304, $response->httpCode());
        $this->assertSame('', $response->body());
        $this->assertEquals(
            ['ETag: ' . $this->etag(), 'Last-Modified: ' . self::MTIME_DATE],
            $response->headers()
        );
    }

    public function testMatchingIfModifiedSinceReturns304()
    {
        $response = $this->handle(true, 'GET', '/test.txt', ['If-Modified-Since' => self::MTIME_DATE]);

        $this->assertEquals(304, $response->httpCode());
        $this->assertSame('', $response->body());
    }

    public function testStaleEtagReturns200WithValidators()
    {
        $response = $this->handle(true, 'GET', '/test.txt', ['If-None-Match' => '"stale"']);

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertContains('ETag: ' . $this->etag(), $response->headers());
    }

    public function testFileReplacedInPlaceReturns200()
    {
        $etag = $this->etag();
        file_put_contents($this->testDir . '/test.txt', 'Hello New World');
        touch($this->testDir . '/test.txt', self::MTIME + 10);

        $response = $this->handle(true, 'GET', '/test.txt', ['If-None-Match' => $etag]);

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello New World', $response->body());
    }

    public function testMissingFileReturns404WithOptionOn()
    {
        $response = $this->handle(true, 'GET', '/missing.txt', ['If-None-Match' => '*']);

        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testTraversalPathReturns403WithOptionOn()
    {
        $response = $this->handle(true, 'GET', '../etc/passwd', ['If-None-Match' => '*']);

        $this->assertInstanceOf(ForbiddenResponse::class, $response);
        $this->assertEquals(403, $response->httpCode());
    }

    public function testHeadWithOptionOnIsUnchanged()
    {
        $headers = ['If-None-Match' => $this->etag()];

        $this->assertResponsesEqual(
            $this->handle(false, 'HEAD', '/test.txt', $headers),
            $this->handle(true, 'HEAD', '/test.txt', $headers)
        );
    }

    public function testPostWithOptionOnIsUnchanged()
    {
        $headers = ['If-None-Match' => $this->etag()];

        $this->assertResponsesEqual(
            $this->handle(false, 'POST', '/test.txt', $headers),
            $this->handle(true, 'POST', '/test.txt', $headers)
        );
    }

    public function testLowerCaseGetMethodIsConditional()
    {
        $response = $this->handle(true, 'get', '/test.txt', ['If-None-Match' => $this->etag()]);

        $this->assertEquals(304, $response->httpCode());
    }

    public function testResponseMiddlewaresRunOn304()
    {
        $handler = new StaticFileHandler(new FolderLocation($this->testDir), true);
        $handler->addMiddleware(new class extends Middleware {
            public function processResponse(Response $response): Response
            {
                $response->setHeaders(array_merge($response->headers(), ['Cache-Control: no-cache']));
                return $response;
            }
        });

        $response = $handler->handleRequest($this->processingRequest(
            'GET',
            '/test.txt',
            ['If-None-Match' => $this->etag()]
        ));

        $this->assertEquals(304, $response->httpCode());
        $this->assertContains('Cache-Control: no-cache', $response->headers());
        $this->assertContains('ETag: ' . $this->etag(), $response->headers());
    }

    private function handle(bool $conditional, string $method, string $path, array $headers = []): Response
    {
        $handler = new StaticFileHandler(new FolderLocation($this->testDir), $conditional);

        return $handler->handleRequest($this->processingRequest($method, $path, $headers));
    }

    private function processingRequest(string $method, string $path, array $headers): ProcessingRequest
    {
        $request = new Request([
            'requestMethod' => $method,
            'requestPath' => $path,
            'headers' => $headers
        ]);

        return new ProcessingRequest(['request' => $request]);
    }

    private function etag(): string
    {
        return '"' . md5(filesize($this->testDir . '/test.txt') . '-' . self::MTIME) . '"';
    }

    private function assertResponsesEqual(Response $expected, Response $actual): void
    {
        $this->assertEquals($expected->httpCode(), $actual->httpCode());
        $this->assertSame($expected->body(), $actual->body());
        $this->assertSame($expected->headers(), $actual->headers());
    }
}
