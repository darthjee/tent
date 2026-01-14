<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Rule;
use Tent\RequestMatcher;
use Tent\Request;

require_once __DIR__ . '/../../../support/tests_loader.php';

class RuleTest extends TestCase
{
    public function testMatchReturnsTrueWhenAMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/test', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertTrue($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('PUT', '/test', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatchers()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $rule = new Rule($handler, []);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenMatchersNotProvided()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $rule = new Rule($handler);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsTrueOnFirstMatch()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('GET', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/other', 'exact');

        $rule = new Rule($handler, [$matcher1, $matcher2]);

        $this->assertTrue($rule->match($request));
    }

    public function testHandlerReturnsTheHandler()
    {
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $rule = new Rule($handler);

        $this->assertSame($handler, $rule->handler());
    }
}
