<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\ResponseHeaderMatcher;
use Tent\Models\Response;

class ResponseHeaderMatcherTest extends TestCase
{
    private function mockResponse(array $headers): Response
    {
        return new Response(['headers' => $headers]);
    }

    public function testMatchResponseReturnsTrueWhenHeaderExists()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache']);
        $response = $this->mockResponse(['X-SaveCache: true', 'Content-Type: text/html']);
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseWhenHeaderDoesNotExist()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache']);
        $response = $this->mockResponse(['Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseIsCaseInsensitive()
    {
        $matcher = new ResponseHeaderMatcher(['x-savecache']);
        $response = $this->mockResponse(['X-SaveCache: true']);
        $this->assertTrue($matcher->matchResponse($response));

        $matcher = new ResponseHeaderMatcher(['X-SaveCache']);
        $response = $this->mockResponse(['x-savecache: true']);
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseWithMultipleHeaders()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache', 'X-Cache-This']);
        $response = $this->mockResponse(['X-Cache-This: yes']);
        $this->assertTrue($matcher->matchResponse($response));

        $response = $this->mockResponse(['X-SaveCache: true']);
        $this->assertTrue($matcher->matchResponse($response));

        $response = $this->mockResponse(['Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseWithEmptyHeaderNames()
    {
        $matcher = new ResponseHeaderMatcher([]);
        $response = $this->mockResponse(['X-SaveCache: true', 'Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testBuildCreatesMatcherWithGivenHeaders()
    {
        $matcher = ResponseHeaderMatcher::build(['headerNames' => ['X-SaveCache']]);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(['X-SaveCache: true'])));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['Content-Type: text/html'])));
    }

    public function testBuildDefaultsToEmptyArray()
    {
        $matcher = ResponseHeaderMatcher::build([]);
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['X-SaveCache: true'])));
    }
}
