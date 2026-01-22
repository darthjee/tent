<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\Response;

class ResponseTest extends TestCase
{
    public function testConstructorAndGetters()
    {
        $body = 'Hello, world!';
        $httpCode = 200;
        $headerLines = ['Content-Type: text/plain'];

        $response = new Response($body, $httpCode, $headerLines);

        $this->assertEquals($body, $response->body());
        $this->assertEquals($httpCode, $response->httpCode());
        $this->assertEquals($headerLines, $response->headerLines());
    }

    public function testSetBody()
    {
        $response = new Response('foo', 200, []);
        $response->setBody('bar');
        $this->assertEquals('bar', $response->body());
    }

    public function testSetHttpCode()
    {
        $response = new Response('foo', 200, []);
        $response->setHttpCode(404);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testSetHeaderLines()
    {
        $response = new Response('foo', 200, []);
        $headers = ['X-Test: ok', 'Content-Type: text/html'];
        $response->setHeaderLines($headers);
        $this->assertEquals($headers, $response->headerLines());
    }

    public function testChainedSetters()
    {
        $response = new Response('foo', 200, []);
        $response->setBody('bar');
        $response->setHttpCode(201);
        $response->setHeaderLines(['A: B']);
        $this->assertEquals('bar', $response->body());
        $this->assertEquals(201, $response->httpCode());
        $this->assertEquals(['A: B'], $response->headerLines());
    }
}
