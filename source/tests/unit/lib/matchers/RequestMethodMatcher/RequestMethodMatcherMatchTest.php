<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestMethodMatcherMatchTest extends TestCase
{
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

    public function testMatchResponseAlwaysReturnsTrue()
    {
        $matcher = new RequestMethodMatcher(['GET']);
        $response = $this->mockResponse(200, 'GET');

        // RequestMethodMatcher should always return true for response matching
        // as it only cares about request methods
        $this->assertTrue($matcher->matchResponse($response));
    }

    public function testMatchResponseReturnsTrueForAnyStatusCode()
    {
        $matcher = new RequestMethodMatcher(['POST', 'PUT']);

        // Should return true for any status code
        $this->assertTrue($matcher->matchResponse($this->mockResponse(200, 'POST')));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(201, 'PUT')));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(500, 'POST')));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(404, 'PUT')));
    }

    private function mockRequest($method)
    {
        return new Request(['requestMethod' => $method]);
    }

    private function mockResponse($httpCode, $method)
    {
        return new Response(['httpCode' => $httpCode, 'request' => $this->mockRequest($method)]);
    }
}
