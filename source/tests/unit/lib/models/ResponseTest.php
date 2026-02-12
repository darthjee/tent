<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Response;
use Tent\Models\Request;

class ResponseTest extends TestCase
{
    public function testConstructorAndGetters()
    {
        $body = 'Hello, world!';
        $httpCode = 200;
        $headerLines = ['Content-Type: text/plain'];
        $request = new Request([]);

        $response = new Response([
            'body' => $body,
            'httpCode' => $httpCode,
            'headers' => $headerLines,
            'request' => $request
        ]);

        $this->assertEquals($body, $response->body());
        $this->assertEquals($httpCode, $response->httpCode());
        $this->assertEquals($headerLines, $response->headers());
        $this->assertEquals($request, $response->request());
    }

    public function testSetBody()
    {
        $request = new Request([]);
        $response = new Response([
            'body' => 'foo', 'httpCode' => 200, 'headers' => [], 'request' => $request
        ]);
        $response->setBody('bar');
        $this->assertEquals('bar', $response->body());
    }

    public function testSetHttpCode()
    {
        $request = new Request([]);
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => [], 'request' => $request]);
        $response->setHttpCode(404);
        $this->assertEquals(404, $response->httpCode());
    }

    public function testSetHeaders()
    {
        $request = new Request([]);
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => [], 'request' => $request]);
        $headers = ['X-Test: ok', 'Content-Type: text/html'];
        $response->setHeaders($headers);
        $this->assertEquals($headers, $response->headers());
    }

    public function testChainedSetters()
    {
        $request = new Request([]);
        $response = new Response(['body' => 'foo', 'httpCode' => 200, 'headers' => [], 'request' => $request]);
        $response->setBody('bar');
        $response->setHttpCode(201);
        $response->setHeaders(['A: B']);
        $this->assertEquals('bar', $response->body());
        $this->assertEquals(201, $response->httpCode());
        $this->assertEquals(['A: B'], $response->headers());
    }
}
