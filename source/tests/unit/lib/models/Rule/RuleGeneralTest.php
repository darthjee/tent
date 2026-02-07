<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\Rule;
use Tent\Models\RequestMatcher;
use Tent\Models\Request;
use Tent\RequestHandlers\RequestHandler;

class RuleGeneralTest extends TestCase
{
    public function testMatchReturnsTrueWhenAMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/test');

        $handler = $this->createMock(RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/test', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertTrue($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/test');

        $handler = $this->createMock(RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('PUT', '/test', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatchers()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule($handler, []);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenMatchersNotProvided()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule($handler);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsTrueOnFirstMatch()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/test');

        $handler = $this->createMock(RequestHandler::class);

        $matcher1 = new RequestMatcher('GET', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/other', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertTrue($rule->match($request));
    }

    public function testHandlerReturnsTheHandler()
    {
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule($handler);

        $this->assertSame($handler, $rule->handler());
    }
}
