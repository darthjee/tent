<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class RequestResponseMatcherBuildTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildCreatesStatusCodeMatcher()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => StatusCodeMatcher::class,
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matcher);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(201)));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(202)));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(200)));
    }

    public function testBuildCreatesStatusCodeMatcherByString()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => "Tent\Matchers\StatusCodeMatcher",
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matcher);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(201)));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(202)));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(200)));
    }

    public function testBuildDefaultsTo200()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => StatusCodeMatcher::class
        ]);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(200)));
        $this->assertFalse($matcher->matchResponse($this->mockResponse(201)));
    }
}
