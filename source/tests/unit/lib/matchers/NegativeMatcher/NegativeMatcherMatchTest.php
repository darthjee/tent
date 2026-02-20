<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\NegativeMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Request;
use Tent\Models\Response;

class NegativeMatcherMatchTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    private function mockRequest($method)
    {
        return new Request(['requestMethod' => $method]);
    }

    public function testMatchResponseReturnsFalseWhenWrappedMatcherReturnsTrue()
    {
        $wrapped = new StatusCodeMatcher([200]);
        $matcher = new NegativeMatcher($wrapped);
        $this->assertFalse($matcher->matchResponse($this->mockResponse(200)));
    }

    public function testMatchResponseReturnsTrueWhenWrappedMatcherReturnsFalse()
    {
        $wrapped = new StatusCodeMatcher([200]);
        $matcher = new NegativeMatcher($wrapped);
        $this->assertTrue($matcher->matchResponse($this->mockResponse(404)));
    }

    public function testMatchRequestReturnsFalseWhenWrappedMatcherReturnsTrue()
    {
        $wrapped = new RequestMethodMatcher(['GET']);
        $matcher = new NegativeMatcher($wrapped);
        $this->assertFalse($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testMatchRequestReturnsTrueWhenWrappedMatcherReturnsFalse()
    {
        $wrapped = new RequestMethodMatcher(['GET']);
        $matcher = new NegativeMatcher($wrapped);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
    }
}
