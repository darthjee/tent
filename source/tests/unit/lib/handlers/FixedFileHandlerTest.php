<?php

namespace Tent\Tests;

use Tent\FixedFileHandler;
use Tent\Request;
use Tent\Response;

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
}
