<?php

namespace Tent\Tests;

use Tent\Handlers\FixedFileHandler;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;

require_once __DIR__ . '/../../../../source/loader.php';

class FixedFileHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testReturnsHtmlFileContent()
    {
        $handler = new FixedFileHandler('./tests/fixtures/content.html');

        $request = $this->createMock(Request::class);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertStringContainsString('Hello, FixedFileHandler!', $response->body);
        $this->assertContains('Content-Type: text/html', $response->headerLines);
    }

    public function testReturnsJsonFileContent()
    {
        $handler = new FixedFileHandler('./tests/fixtures/data.json');
        $request = $this->createMock(Request::class);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertStringContainsString('Hello, JSON!', $response->body);
        $this->assertContains('Content-Type: application/json', $response->headerLines);
    }

    public function testReturnsImageFileContent()
    {
        $handler = new FixedFileHandler('./tests/fixtures/image.gif');
        $request = $this->createMock(Request::class);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertNotEmpty($response->body);
        $this->assertContains('Content-Type: image/gif', $response->headerLines);
    }

    public function testReturnsMissingResponseWhenFileNotFound()
    {
        $handler = new FixedFileHandler('./tests/fixtures/nonexistent.txt');
        $request = $this->createMock(Request::class);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(MissingResponse::class, $response);
    }

    public function testReturnsCssFileContent()
    {
        $handler = new FixedFileHandler('./tests/fixtures/style.css');
        $request = $this->createMock(Request::class);
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertStringContainsString('background: #fff', $response->body);
        $this->assertContains('Content-Type: text/css', $response->headerLines);
    }

    public function testReturnsForbiddenResponseForPathTraversal()
    {
        $handler = new FixedFileHandler('./tests/fixtures/content.html');
        $request = $this->createMock(Request::class);
        $request = new Request(['requestUrl' => '../etc/passwd']);

        $response = $handler->handleRequest($request);
        $this->assertInstanceOf(ForbiddenResponse::class, $response);
        $this->assertEquals(403, $response->httpCode);
    }
}
