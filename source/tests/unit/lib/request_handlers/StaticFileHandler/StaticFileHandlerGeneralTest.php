<?php

namespace Tent\Tests\RequestHandlers\StaticFileHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\StaticFileHandler;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\Request;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Utils\FileSystemUtils;

class StaticFileHandlerGeneralTest extends TestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tent_test_' . uniqid();
        mkdir($this->testDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testHandleRequestReturnsFileContent()
    {
        file_put_contents($this->testDir . '/test.txt', 'Hello World');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/test.txt');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertContains('Content-Type: text/plain', $response->headers());
    }

    public function testHandleRequestReturnsMissingResponseWhenFileDoesNotExist()
    {
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/nonexistent.txt');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testHandleRequestReturnsCorrectContentTypeForHtml()
    {
        file_put_contents($this->testDir . '/index.html', '<h1>Test</h1>');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/index.html');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: text\/html/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForCss()
    {
        file_put_contents($this->testDir . '/style.css', 'body { margin: 0; }');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/style.css');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: text\/css/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJs()
    {
        file_put_contents($this->testDir . '/script.js', 'console.log("test");');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/script.js');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: application\/javascript/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJson()
    {
        file_put_contents($this->testDir . '/data.json', '{"key": "value"}');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/data.json');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: application\/json/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForPng()
    {
        copy(__DIR__ . '/../../../../fixtures/test_image.png', $this->testDir . '/image.png');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/image.png');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: image\/png/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJpg()
    {
        copy(__DIR__ . '/../../../../fixtures/test_image.jpg', $this->testDir . '/image.jpg');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/image.jpg');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headers());
        $this->assertMatchesRegularExpression('/Content-Type: image\/jpeg/', $response->headers()[0]);
    }

    public function testHandleRequestReturnsMissingResponseForDirectory()
    {
        mkdir($this->testDir . '/subdir');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/subdir');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);

        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testHandleRequestReturnsForbiddenResponseForPathTraversal()
    {
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);
        $request = new Request(['requestPath' => '../etc/passwd']);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $response = $handler->handleRequest($processingRequest);
        $this->assertInstanceOf(ForbiddenResponse::class, $response);
        $this->assertEquals(403, $response->httpCode());
    }

    public function testLogsDebugWhenFileNotFound(): void
    {
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with(
                '404: static file not found — uri: /nonexistent.txt, resolved path: ' .
                $this->testDir . '/nonexistent.txt',
                'debug'
            );
        Logger::setInstance($instance);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/nonexistent.txt');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $handler->handleRequest($processingRequest);
    }

    public function testLogsDebugWhenDirectoryRequested(): void
    {
        mkdir($this->testDir . '/subdir');
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with(
                '404: static file not found — uri: /subdir, resolved path: ' .
                $this->testDir . '/subdir',
                'debug'
            );
        Logger::setInstance($instance);

        $request = $this->createMock(Request::class);
        $request->method('requestPath')->willReturn('/subdir');
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $handler->handleRequest($processingRequest);
    }
}
