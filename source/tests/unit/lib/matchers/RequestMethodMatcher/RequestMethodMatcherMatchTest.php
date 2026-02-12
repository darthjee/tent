<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestMethodMatcherMatchTest extends TestCase
{
    private function mockResponse($method)
    {
        $request = new Request(['requestMethod' => $method]);
        return new Response(['httpCode' => 200, 'request' => $request]);
    }

    public function testMatchReturnsTrueWhenMethodIsInList()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertTrue($matcher->match($this->mockResponse('GET')));

        $matcher = new RequestMethodMatcher(['POST']);
        $this->assertTrue($matcher->match($this->mockResponse('POST')));
    }

    public function testMatchReturnsFalseWhenMethodIsNotInList()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertFalse($matcher->match($this->mockResponse('POST')));

        $matcher = new RequestMethodMatcher(['POST']);
        $this->assertFalse($matcher->match($this->mockResponse('GET')));
    }

    public function testMatchIsCaseInsensitive()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $this->assertTrue($matcher->match($this->mockResponse('get')));
        $this->assertTrue($matcher->match($this->mockResponse('Get')));
        $this->assertTrue($matcher->match($this->mockResponse('GET')));
    }

    public function testMatchWithMultipleMethods()
    {
        $matcher = new RequestMethodMatcher(['GET', 'POST']);
        $this->assertTrue($matcher->match($this->mockResponse('GET')));
        $this->assertTrue($matcher->match($this->mockResponse('POST')));
        $this->assertFalse($matcher->match($this->mockResponse('PUT')));
    }
}
