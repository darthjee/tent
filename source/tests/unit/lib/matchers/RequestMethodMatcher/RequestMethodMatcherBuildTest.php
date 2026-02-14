<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestMethodMatcherBuildTest extends TestCase
{
    private function mockRequest($method)
    {
        return new Request(['requestMethod' => $method]);
    }

    public function testBuildCreatesMatcherWithGivenMethods()
    {
        $matcher = RequestMethodMatcher::build(['requestMethods' => ['POST', 'PUT']]);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('PUT')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testBuildDefaultsToGet()
    {
        $matcher = RequestMethodMatcher::build([]);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('GET')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('POST')));
    }
}
