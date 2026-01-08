<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\ProxyTarget;
use Tent\RequestMatcher;
use Tent\Request;

require_once __DIR__ . '/../../../../source/lib/models/ProxyTarget.php';
require_once __DIR__ . '/../../../../source/lib/models/RequestMatcher.php';
require_once __DIR__ . '/../../../../source/lib/models/Request.php';
require_once __DIR__ . '/../../../../source/lib/handlers/RequestHandler.php';

class ProxyTargetTest extends TestCase
{
    public function testMatchReturnsTrueWhenAMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/test', 'exact');

        $target = new ProxyTarget($handler, [$matcher1, $matcher2]);

        $this->assertTrue($target->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatcherMatches()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('POST', '/test', 'exact');
        $matcher2 = new RequestMatcher('PUT', '/test', 'exact');

        $target = new ProxyTarget($handler, [$matcher1, $matcher2]);

        $this->assertFalse($target->match($request));
    }

    public function testMatchReturnsFalseWhenNoMatchers()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $target = new ProxyTarget($handler, []);

        $this->assertFalse($target->match($request));
    }

    public function testMatchReturnsFalseWhenMatchersNotProvided()
    {
        $request = $this->createMock(Request::class);
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $target = new ProxyTarget($handler);

        $this->assertFalse($target->match($request));
    }

    public function testMatchReturnsTrueOnFirstMatch()
    {
        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestUrl')->willReturn('/test');

        $handler = $this->createMock(\Tent\RequestHandler::class);

        $matcher1 = new RequestMatcher('GET', '/test', 'exact');
        $matcher2 = new RequestMatcher('GET', '/other', 'exact');

        $target = new ProxyTarget($handler, [$matcher1, $matcher2]);

        $this->assertTrue($target->match($request));
    }

    public function testHandlerReturnsTheHandler()
    {
        $handler = $this->createMock(\Tent\RequestHandler::class);

        $target = new ProxyTarget($handler);

        $this->assertSame($handler, $target->handler());
    }
}
