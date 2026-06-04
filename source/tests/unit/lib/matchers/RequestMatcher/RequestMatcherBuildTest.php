<?php

namespace Tent\Tests\Matchers\RequestMatcher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMatcher;
use Tent\Matchers\ExactRequestMatcher;
use Tent\Matchers\BeginsWithRequestMatcher;
use Tent\Matchers\EndsWithRequestMatcher;
use Tent\Matchers\RegexRequestMatcher;
use Tent\Models\Request;

class RequestMatcherBuildTest extends TestCase
{
    public function testBuildCreatesRequestMatcherWithAllFields()
    {
        $matcher = RequestMatcher::build([
            'method' => 'GET',
            'uri' => '/users',
            'type' => 'exact'
        ]);
        $this->assertInstanceOf(RequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/users');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildDefaultsTypeToExact()
    {
        $matcher = RequestMatcher::build([
            'method' => 'POST',
            'uri' => '/api',
        ]);
        $this->assertInstanceOf(RequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('POST');
        $request->method('requestPath')->willReturn('/api');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildMatchersCreatesMultipleMatchers()
    {
        $attributes = [
            ['method' => 'GET', 'uri' => '/users', 'type' => 'exact'],
            ['method' => 'POST', 'uri' => '/users', 'type' => 'begins_with'],
            ['method' => null, 'uri' => '/admin', 'type' => 'exact'],
            ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with'],
            ['method' => 'GET', 'pattern' => '/^\/profiles\/.+$/', 'type' => 'regex'],
        ];

        $matchers = RequestMatcher::buildMatchers($attributes);

        $this->assertCount(5, $matchers);
        $this->assertInstanceOf(ExactRequestMatcher::class, $matchers[0]);
        $this->assertInstanceOf(BeginsWithRequestMatcher::class, $matchers[1]);
        $this->assertInstanceOf(ExactRequestMatcher::class, $matchers[2]);
        $this->assertInstanceOf(EndsWithRequestMatcher::class, $matchers[3]);
        $this->assertInstanceOf(RegexRequestMatcher::class, $matchers[4]);
    }

    public function testBuildEndsWithMatcher()
    {
        $matcher = RequestMatcher::build([
            'method' => 'GET',
            'uri' => '.json',
            'type' => 'ends_with'
        ]);
        $this->assertInstanceOf(EndsWithRequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/api/data.json');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildThrowsExceptionForInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown matcher type 'invalid_type'.");

        RequestMatcher::build([
            'method' => 'GET',
            'uri' => '/users',
            'type' => 'invalid_type'
        ]);
    }

    public function testBuildRegexMatcher()
    {
        $matcher = RequestMatcher::build([
            'method' => 'GET',
            'type' => 'regex',
            'pattern' => '/^\/users\/\d+$/'
        ]);

        $this->assertInstanceOf(RegexRequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/users/123');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildRegexMatcherThrowsOnInvalidPattern()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid regex pattern '/(invalid/'.");

        RequestMatcher::build([
            'method' => 'GET',
            'type' => 'regex',
            'pattern' => '/(invalid/'
        ]);
    }
}
