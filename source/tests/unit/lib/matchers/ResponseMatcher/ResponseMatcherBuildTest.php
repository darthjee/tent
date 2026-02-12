<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\ResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class ResponseMatcherBuildTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildCreatesStatusCodeMatcher()
    {
        $matcher = ResponseMatcher::build([
            'class' => StatusCodeMatcher::class,
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matcher);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $this->assertTrue($matcher->match($this->mockResponse(202)));
        $this->assertFalse($matcher->match($this->mockResponse(200)));
    }

    public function testBuildCreatesStatusCodeMatcherByString()
    {
        $matcher = ResponseMatcher::build([
            'class' => "Tent\Matchers\StatusCodeMatcher",
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matcher);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $this->assertTrue($matcher->match($this->mockResponse(202)));
        $this->assertFalse($matcher->match($this->mockResponse(200)));
    }

    public function testBuildDefaultsTo200()
    {
        $matcher = ResponseMatcher::build([
            'class' => StatusCodeMatcher::class
        ]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
        $this->assertFalse($matcher->match($this->mockResponse(201)));
    }
}
