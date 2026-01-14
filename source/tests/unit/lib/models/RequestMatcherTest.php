<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\RequestMatcher;
use Tent\Request;

class RequestMatcherTest extends TestCase
{
    public function testMatchesWithExactMatch()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new RequestMatcher('GET', '/home', 'exact');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithDifferentMethod()
    {
        $request = $this->createMockRequest('POST', '/home');
        $matcher = new RequestMatcher('GET', '/home', 'exact');

        $this->assertFalse($matcher->matches($request));
    }

    public function testDoesNotMatchWithDifferentUrlExact()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new RequestMatcher('GET', '/about', 'exact');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesWithBeginsWithPattern()
    {
        $request = $this->createMockRequest('GET', '/assets/js/main.js');
        $matcher = new RequestMatcher('GET', '/assets/js/', 'begins_with');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithBeginsWithWhenNotStarting()
    {
        $request = $this->createMockRequest('GET', '/home/assets/js/main.js');
        $matcher = new RequestMatcher('GET', '/assets/js/', 'begins_with');

        $this->assertFalse($matcher->matches($request));
    }

    public function testDefaultMatchTypeIsExact()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new RequestMatcher('GET', '/home');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithInvalidMatchType()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new RequestMatcher('GET', '/home', 'invalid_type');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesRootPathExactly()
    {
        $request = $this->createMockRequest('GET', '/');
        $matcher = new RequestMatcher('GET', '/', 'exact');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchRootWithBeginsWithForDifferentPath()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new RequestMatcher('GET', '/', 'begins_with');

        $this->assertTrue($matcher->matches($request)); // All paths begin with '/'
    }

    public function testMatchesPathOnlyWhenMethodIsNull()
    {
        $request = $this->createMockRequest('POST', '/home');
        $matcher = new RequestMatcher(null, '/home', 'exact');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesPathOnlyWithBeginsWithWhenMethodIsNull()
    {
        $request = $this->createMockRequest('DELETE', '/assets/js/main.js');
        $matcher = new RequestMatcher(null, '/assets/js/', 'begins_with');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWhenMethodIsNullAndPathDifferent()
    {
        $request = $this->createMockRequest('PUT', '/about');
        $matcher = new RequestMatcher(null, '/home', 'exact');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesMethodOnlyWhenUriIsNull()
    {
        $request = $this->createMockRequest('GET', '/any/path');
        $matcher = new RequestMatcher('GET', null);

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesMethodOnlyWithDifferentPathsWhenUriIsNull()
    {
        $request = $this->createMockRequest('POST', '/completely/different');
        $matcher = new RequestMatcher('POST', null);

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWhenUriIsNullAndMethodDifferent()
    {
        $request = $this->createMockRequest('DELETE', '/home');
        $matcher = new RequestMatcher('GET', null);

        $this->assertFalse($matcher->matches($request));
    }

    private function createMockRequest($method, $url)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestUrl')->willReturn($url);
        return $mock;
    }
}
