<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\StaticFileHandler;
use Tent\Models\FolderLocation;
use Tent\Models\Request;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;

class StaticFileHandlerGeneralTest extends TestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tent_test_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testHandleRequestReturnsFileContent()
    {
        file_put_contents($this->testDir . '/test.txt', 'Hello World');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/test.txt');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertContains('Content-Type: text/plain', $response->headerLines());
    }

    public function testHandleRequestReturnsMissingResponseWhenFileDoesNotExist()
    {
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/nonexistent.txt');

        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testHandleRequestReturnsCorrectContentTypeForHtml()
    {
        file_put_contents($this->testDir . '/index.html', '<h1>Test</h1>');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/index.html');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: text\/html/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForCss()
    {
        file_put_contents($this->testDir . '/style.css', 'body { margin: 0; }');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/style.css');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: text\/css/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJs()
    {
        file_put_contents($this->testDir . '/script.js', 'console.log("test");');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/script.js');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: application\/javascript/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJson()
    {
        file_put_contents($this->testDir . '/data.json', '{"key": "value"}');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/data.json');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: application\/json/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForPng()
    {
        copy(__DIR__ . '/../../../../fixtures/test_image.png', $this->testDir . '/image.png');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/image.png');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: image\/png/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsCorrectContentTypeForJpg()
    {
        copy(__DIR__ . '/../../../../fixtures/test_image.jpg', $this->testDir . '/image.jpg');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/image.jpg');

        $response = $handler->handleRequest($request);

        $this->assertEquals(200, $response->httpCode());
        $this->assertCount(2, $response->headerLines());
        $this->assertMatchesRegularExpression('/Content-Type: image\/jpeg/', $response->headerLines()[0]);
    }

    public function testHandleRequestReturnsMissingResponseForDirectory()
    {
        mkdir($this->testDir . '/subdir');

        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);

        $request = $this->createMock(Request::class);
        $request->method('requestUrl')->willReturn('/subdir');

        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(MissingResponse::class, $response);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testHandleRequestReturnsForbiddenResponseForPathTraversal()
    {
        $location = new FolderLocation($this->testDir);
        $handler = new StaticFileHandler($location);
        $request = new Request(['requestUrl' => '../etc/passwd']);

        $response = $handler->handleRequest($request);
        $this->assertInstanceOf(ForbiddenResponse::class, $response);
        $this->assertEquals(403, $response->httpCode());
    }
}
