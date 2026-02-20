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

    public function testMatchResponseReturnsTrueWhenHeaderExistsWithMatchingValue()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['X-SaveCache: true', 'Content-Type: text/html']);
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseWhenHeaderDoesNotExist()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseWhenHeaderExistsWithNonMatchingValue()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['X-SaveCache: false', 'Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseHeaderNameIsCaseInsensitive()
    {
        $matcher = new ResponseHeaderMatcher(['x-savecache' => 'true']);
        $response = $this->mockResponse(['X-SaveCache: true']);
        $this->assertTrue($matcher->matchResponse($response));

        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['x-savecache: true']);
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseHeaderValueIsCaseSensitive()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['X-SaveCache: True']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseTrimsWhitespaceFromValues()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true']);
        $response = $this->mockResponse(['X-SaveCache:  true ']);
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseWithMultipleConfiguredHeadersReturnsTrueIfOneMatches()
    {
        $matcher = new ResponseHeaderMatcher(['X-SaveCache' => 'true', 'X-Cache-This' => 'yes']);
        $response = $this->mockResponse(['X-Cache-This: yes']);
        $this->assertTrue($matcher->matchResponse($response));

        $response = $this->mockResponse(['X-SaveCache: true']);
        $this->assertTrue($matcher->matchResponse($response));

        $response = $this->mockResponse(['Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testMatchResponseWithEmptyHeaders()
    {
        $matcher = new ResponseHeaderMatcher([]);
        $response = $this->mockResponse(['X-SaveCache: true', 'Content-Type: text/html']);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testBuildCreatesMatcherWithGivenHeaders()
    {
        $matcher = ResponseHeaderMatcher::build(['headers' => ['X-SaveCache' => 'true']]);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(['X-SaveCache: true'])));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['X-SaveCache: false'])));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['Content-Type: text/html'])));
    }

    public function testBuildCreatesMatcherWithMultipleHeadersUsingOrLogic()
    {
        $matcher = ResponseHeaderMatcher::build([
            'headers' => ['X-SaveCache' => 'true', 'X-Cache-This' => 'some_other_value']
        ]);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(['X-SaveCache: true'])));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(['X-Cache-This: some_other_value'])));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['Content-Type: text/html'])));
    }

    public function testBuildDefaultsToEmptyArray()
    {
        $matcher = ResponseHeaderMatcher::build([]);
        $this->assertFalse($matcher->matchResponse($this->mockResponse(['X-SaveCache: true'])));
    }
}
