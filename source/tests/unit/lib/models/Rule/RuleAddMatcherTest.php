<?php

namespace Tent\Tests\Models\Rule;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Rule;
use Tent\Models\Request;
use Tent\Matchers\ExactRequestMatcher;
use Tent\RequestHandlers\RequestHandler;

class RuleAddMatcherTest extends TestCase
{
    public function testAddMatcherMakesRequestMatch()
    {
        $handler = $this->createMock(RequestHandler::class);

        $matcher = new ExactRequestMatcher('GET', '/persons');
        $rule = new Rule(['handler' => $handler, 'matchers' => [$matcher]]);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('POST');
        $request->method('requestPath')->willReturn('/persons');

        $this->assertFalse($rule->match($request));

        $rule->addMatcher(['method' => 'POST', 'uri' => '/persons', 'type' => 'exact']);

        $this->assertTrue($rule->match($request));
    }

    public function testAddMatcherPreservesExistingMatchers()
    {
        $handler = $this->createMock(RequestHandler::class);

        $matcher = new ExactRequestMatcher('GET', '/persons');
        $rule = new Rule(['handler' => $handler, 'matchers' => [$matcher]]);

        $rule->addMatcher(['method' => 'POST', 'uri' => '/persons', 'type' => 'exact']);

        $getRequest = $this->createMock(Request::class);
        $getRequest->method('requestMethod')->willReturn('GET');
        $getRequest->method('requestPath')->willReturn('/persons');

        $postRequest = $this->createMock(Request::class);
        $postRequest->method('requestMethod')->willReturn('POST');
        $postRequest->method('requestPath')->willReturn('/persons');

        $this->assertTrue($rule->match($getRequest));
        $this->assertTrue($rule->match($postRequest));
    }

    public function testAddMatcherWorksOnRuleWithNoInitialMatchers()
    {
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule(['handler' => $handler]);

        $rule->addMatcher(['method' => 'GET', 'uri' => '/health', 'type' => 'exact']);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/health');

        $this->assertTrue($rule->match($request));
    }
}
