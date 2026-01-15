<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Utils\CurlUtils;

class CurlUtilsTest extends TestCase
{
    public function testBuildHeaderLinesWithSingleHeader()
    {
        $headers = ['Content-Type' => 'application/json'];
        $result = CurlUtils::buildHeaderLines($headers);

        $this->assertEquals(['Content-Type: application/json'], $result);
    }

    public function testBuildHeaderLinesWithMultipleHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123',
            'Accept' => 'application/json'
        ];

        $result = CurlUtils::buildHeaderLines($headers);

        $expected = [
            'Content-Type: application/json',
            'Authorization: Bearer token123',
            'Accept: application/json'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testBuildHeaderLinesWithEmptyHeaders()
    {
        $headers = [];
        $result = CurlUtils::buildHeaderLines($headers);

        $this->assertEquals([], $result);
    }

    public function testBuildHeaderLinesWithValueContainingColon()
    {
        $headers = ['X-Custom' => 'value:with:colons'];
        $result = CurlUtils::buildHeaderLines($headers);

        $this->assertEquals(['X-Custom: value:with:colons'], $result);
    }

    public function testBuildHeaderLinesWithEmptyValue()
    {
        $headers = ['X-Empty' => ''];
        $result = CurlUtils::buildHeaderLines($headers);

        $this->assertEquals(['X-Empty: '], $result);
    }

    public function testBuildHeaderLinesWithNumericValue()
    {
        $headers = ['Content-Length' => '1234'];
        $result = CurlUtils::buildHeaderLines($headers);

        $this->assertEquals(['Content-Length: 1234'], $result);
    }

    public function testParseResponseHeadersWithMultipleHeaders()
    {
        $headers = "HTTP/1.1 200 OK\nContent-Type: application/json\nContent-Length: 123\nCache-Control: no-cache";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [
            1 => 'Content-Type: application/json',
            2 => 'Content-Length: 123',
            3 => 'Cache-Control: no-cache'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseResponseHeadersRemovesHttpStatusLine()
    {
        $headers = "HTTP/1.1 404 Not Found\nContent-Type: text/plain";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [1 => 'Content-Type: text/plain'];

        $this->assertEquals($expected, $result);
    }

    public function testParseResponseHeadersRemovesEmptyLines()
    {
        $headers = "HTTP/1.1 200 OK\nContent-Type: application/json\n\n\nCache-Control: no-cache\n";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [
            1 => 'Content-Type: application/json',
            4 => 'Cache-Control: no-cache'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseResponseHeadersTrimsWhitespace()
    {
        $headers = "HTTP/1.1 200 OK\n  Content-Type: application/json  \n  Cache-Control: no-cache  ";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [
            1 => 'Content-Type: application/json',
            2 => 'Cache-Control: no-cache'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseResponseHeadersWithEmptyString()
    {
        $headers = "";
        $result = CurlUtils::parseResponseHeaders($headers);

        $this->assertEquals([], $result);
    }

    public function testParseResponseHeadersWithOnlyHttpLine()
    {
        $headers = "HTTP/1.1 200 OK";
        $result = CurlUtils::parseResponseHeaders($headers);

        $this->assertEquals([], $result);
    }

    public function testParseResponseHeadersWithMultipleHttpLines()
    {
        $headers = "HTTP/1.1 301 Moved Permanently\nLocation: /new-url\nHTTP/1.1 200 OK\nContent-Type: text/html";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [
            1 => 'Location: /new-url',
            3 => 'Content-Type: text/html'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseResponseHeadersWithSingleHeader()
    {
        $headers = "HTTP/1.1 200 OK\nContent-Type: application/json";
        $result = CurlUtils::parseResponseHeaders($headers);

        $expected = [1 => 'Content-Type: application/json'];

        $this->assertEquals($expected, $result);
    }
}
