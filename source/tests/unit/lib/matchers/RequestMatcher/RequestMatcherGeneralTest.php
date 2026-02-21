<?php

namespace Tent\Tests\Matchers\RequestMatcher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMatcher;
use Tent\Matchers\ExactRequestMatcher;
use Tent\Matchers\BeginsWithRequestMatcher;
use Tent\Models\Request;

class RequestMatcherGeneralTest extends TestCase
{
    public function testMatchesWithExactMatch()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new ExactRequestMatcher('GET', '/home');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithDifferentMethod()
    {
        $request = $this->createMockRequest('POST', '/home');
        $matcher = new ExactRequestMatcher('GET', '/home');

        $this->assertFalse($matcher->matches($request));
    }

    public function testDoesNotMatchWithDifferentUrlExact()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new ExactRequestMatcher('GET', '/about');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesWithBeginsWithPattern()
    {
        $request = $this->createMockRequest('GET', '/assets/js/main.js');
        $matcher = new BeginsWithRequestMatcher('GET', '/assets/js/');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithBeginsWithWhenNotStarting()
    {
        $request = $this->createMockRequest('GET', '/home/assets/js/main.js');
        $matcher = new BeginsWithRequestMatcher('GET', '/assets/js/');

        $this->assertFalse($matcher->matches($request));
    }

    public function testDefaultMatchTypeIsExact()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new ExactRequestMatcher('GET', '/home');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesRootPathExactly()
    {
        $request = $this->createMockRequest('GET', '/');
        $matcher = new ExactRequestMatcher('GET', '/');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchRootWithBeginsWithForDifferentPath()
    {
        $request = $this->createMockRequest('GET', '/home');
        $matcher = new BeginsWithRequestMatcher('GET', '/');

        $this->assertTrue($matcher->matches($request)); // All paths begin with '/'
    }

    public function testMatchesPathOnlyWhenMethodIsNull()
    {
        $request = $this->createMockRequest('POST', '/home');
        $matcher = new ExactRequestMatcher(null, '/home');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesPathOnlyWithBeginsWithWhenMethodIsNull()
    {
        $request = $this->createMockRequest('DELETE', '/assets/js/main.js');
        $matcher = new BeginsWithRequestMatcher(null, '/assets/js/');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWhenMethodIsNullAndPathDifferent()
    {
        $request = $this->createMockRequest('PUT', '/about');
        $matcher = new ExactRequestMatcher(null, '/home');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesMethodOnlyWhenUriIsNull()
    {
        $request = $this->createMockRequest('GET', '/any/path');
        $matcher = new ExactRequestMatcher('GET', null);

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesMethodOnlyWithDifferentPathsWhenUriIsNull()
    {
        $request = $this->createMockRequest('POST', '/completely/different');
        $matcher = new ExactRequestMatcher('POST', null);

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWhenUriIsNullAndMethodDifferent()
    {
        $request = $this->createMockRequest('DELETE', '/home');
        $matcher = new ExactRequestMatcher('GET', null);

        $this->assertFalse($matcher->matches($request));
    }

    private function createMockRequest($method, $url)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestPath')->willReturn($url);
        return $mock;
    }
}
