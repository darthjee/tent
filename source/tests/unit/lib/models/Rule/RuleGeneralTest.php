<?php

namespace Tent\Tests\Models\Rule;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Rule;
use Tent\Matchers\ExactRequestMatcher;
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

        $matcher1 = new ExactRequestMatcher('POST', '/test');
        $matcher2 = new ExactRequestMatcher('GET', '/test');

        $rule = new Rule(['handler' => $handler, 'matchers' => [$matcher1, $matcher2]]);

        $this->assertTrue($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/test');

        $handler = $this->createMock(RequestHandler::class);

        $matcher1 = new ExactRequestMatcher('POST', '/test');
        $matcher2 = new ExactRequestMatcher('PUT', '/test');

        $rule = new Rule(['handler' => $handler, 'matchers' => [$matcher1, $matcher2]]);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatchers()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule(['handler' => $handler, 'matchers' => []]);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsFalseWhenMatchersNotProvided()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule(['handler' => $handler]);

        $this->assertFalse($rule->match($request));
    }

    public function testMatchReturnsTrueOnFirstMatch()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/test');

        $handler = $this->createMock(RequestHandler::class);

        $matcher1 = new ExactRequestMatcher('GET', '/test');
        $matcher2 = new ExactRequestMatcher('GET', '/other');

        $rule = new Rule(['handler' => $handler, 'matchers' => [$matcher1, $matcher2]]);

        $this->assertTrue($rule->match($request));
    }

    public function testHandlerReturnsTheHandler()
    {
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule(['handler' => $handler]);

        $this->assertSame($handler, $rule->handler());
    }
}
