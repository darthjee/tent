<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\RequestMatcher;
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
        $request->method('requestUrl')->willReturn('/users');
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
        $request->method('requestUrl')->willReturn('/api');
        $this->assertTrue($matcher->matches($request));
    }
}
