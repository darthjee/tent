<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestMethodMatcherMatchTest extends TestCase
{
    private function mockRequest($method)
    {
        return new Request(['requestMethod' => $method]);
    }

    public function testMatchReturnsTrueWhenMethodIsInList()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('GET')));

        $matcher = new RequestMethodMatcher(['POST']);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
    }

    public function testMatchReturnsFalseWhenMethodIsNotInList()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertFalse($matcher->matchRequest($this->mockRequest('POST')));

        $matcher = new RequestMethodMatcher(['POST']);
        $this->assertFalse($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testMatchIsCaseInsensitive()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('get')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('Get')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('GET')));
    }

    public function testMatchWithMultipleMethods()
    {
        $matcher = new RequestMethodMatcher(['GET', 'POST']);
        $this->assertTrue($matcher->matchRequest($this->mockRequest('GET')));
        $this->assertTrue($matcher->matchRequest($this->mockRequest('POST')));
        $this->assertFalse($matcher->matchRequest($this->mockRequest('PUT')));
    }
}
