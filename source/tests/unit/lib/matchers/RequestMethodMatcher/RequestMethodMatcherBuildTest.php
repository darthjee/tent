<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestMethodMatcherBuildTest extends TestCase
{
    private function mockResponse($method)
    {
        $request = new Request(['requestMethod' => $method]);
        return new Response(['httpCode' => 200, 'request' => $request]);
    }

    public function testBuildCreatesMatcherWithGivenMethods()
    {
        $matcher = RequestMethodMatcher::build(['requestMethods' => ['POST', 'PUT']]);
        $this->assertTrue($matcher->match($this->mockResponse('POST')));
        $this->assertTrue($matcher->match($this->mockResponse('PUT')));
        $this->assertFalse($matcher->match($this->mockResponse('GET')));
    }

    public function testBuildDefaultsToGet()
    {
        $matcher = RequestMethodMatcher::build([]);
        $this->assertTrue($matcher->match($this->mockResponse('GET')));
        $this->assertFalse($matcher->match($this->mockResponse('POST')));
    }
}
