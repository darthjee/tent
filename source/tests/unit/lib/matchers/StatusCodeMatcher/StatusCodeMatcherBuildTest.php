<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class StatusCodeMatcherBuildTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildCreatesMatcherWithGivenCodes()
    {
        $matcher = StatusCodeMatcher::build(['httpCodes' => [201, 202]]);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $this->assertTrue($matcher->match($this->mockResponse(202)));
        $this->assertFalse($matcher->match($this->mockResponse(200)));
    }

    public function testBuildDefaultsTo200()
    {
        $matcher = StatusCodeMatcher::build([]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
        $this->assertFalse($matcher->match($this->mockResponse(201)));
    }
}
