<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\ResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class ResponseMatcherBuildTest extends TestCase
{
    private function mockResponse($code, $method = 'GET')
    {
        $request = new Request(['requestMethod' => $method]);
        return new Response(['httpCode' => $code, 'request' => $request]);
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

    public function testBuildCreatesRequestMethodMatcher()
    {
        $matcher = ResponseMatcher::build([
            'class' => RequestMethodMatcher::class,
            'requestMethods' => ['POST', 'PUT']
        ]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matcher);
        $this->assertTrue($matcher->match($this->mockResponse(200, 'POST')));
        $this->assertTrue($matcher->match($this->mockResponse(200, 'PUT')));
        $this->assertFalse($matcher->match($this->mockResponse(200, 'GET')));
    }

    public function testBuildCreatesRequestMethodMatcherByString()
    {
        $matcher = ResponseMatcher::build([
            'class' => "Tent\Matchers\RequestMethodMatcher",
            'requestMethods' => ['POST', 'PUT']
        ]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matcher);
        $this->assertTrue($matcher->match($this->mockResponse(200, 'POST')));
        $this->assertTrue($matcher->match($this->mockResponse(200, 'PUT')));
        $this->assertFalse($matcher->match($this->mockResponse(200, 'GET')));
    }

    public function testBuildRequestMethodMatcherDefaultsToGet()
    {
        $matcher = ResponseMatcher::build([
            'class' => RequestMethodMatcher::class
        ]);
        $this->assertTrue($matcher->match($this->mockResponse(200, 'GET')));
        $this->assertFalse($matcher->match($this->mockResponse(200, 'POST')));
    }
}
