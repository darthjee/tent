<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestResponseMatcherBuildTest extends TestCase
{
    private function mockResponse($code, $method = 'GET')
    {
        $request = $this->mockRequest($method);
        return new Response(['httpCode' => $code, 'request' => $request]);
    }

    private function mockRequest($method = 'GET')
    {
        return new Request(['requestMethod' => $method]);
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

    public function testBuildCreatesRequestMethodMatcher()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => RequestMethodMatcher::class,
            'requestMethods' => ['POST', 'PUT']
        ]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matcher);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('PUT')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testBuildCreatesRequestMethodMatcherByString()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => "Tent\Matchers\RequestMethodMatcher",
            'requestMethods' => ['POST', 'PUT']
        ]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matcher);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('PUT')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testBuildRequestMethodMatcherDefaultsToGet()
    {
        $matcher = RequestResponseMatcher::build([
            'class' => RequestMethodMatcher::class
        ]);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('GET')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('POST')));
    }
}
