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

        $response = new Response([
            'body' => $body,
            'httpCode' => $httpCode,
            'headers' => $headerLines
        ]);

        $this->assertEquals($body, $response->body());
        $this->assertEquals($httpCode, $response->httpCode());
        $this->assertEquals($headerLines, $response->headerLines());
    }

    public function testSetBody()
    {
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => []]);
        $response->setBody('bar');
        $this->assertEquals('bar', $response->body());
    }

    public function testSetHttpCode()
    {
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => []]);
        $response->setHttpCode(404);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testSetHeaderLines()
    {
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => []]);
        $headers = ['X-Test: ok', 'Content-Type: text/html'];
        $response->setHeaderLines($headers);
        $this->assertEquals($headers, $response->headerLines());
    }

    public function testChainedSetters()
    {
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => []]);
        $response->setBody('bar');
        $response->setHttpCode(201);
        $response->setHeaderLines(['A: B']);
        $this->assertEquals('bar', $response->body());
        $this->assertEquals(201, $response->httpCode());
        $this->assertEquals(['A: B'], $response->headerLines());
    }
}
