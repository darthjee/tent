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

        $request = new Request(['requestMethod' => 'POST', 'requestPath' => '/persons']);

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

        $getRequest = new Request(['requestMethod' => 'GET', 'requestPath' => '/persons']);
        $postRequest = new Request(['requestMethod' => 'POST', 'requestPath' => '/persons']);

        $this->assertTrue($rule->match($getRequest));
        $this->assertTrue($rule->match($postRequest));
    }

    public function testAddMatcherWorksOnRuleWithNoInitialMatchers()
    {
        $handler = $this->createMock(RequestHandler::class);

        $rule = new Rule(['handler' => $handler]);

        $rule->addMatcher(['method' => 'GET', 'uri' => '/health', 'type' => 'exact']);

        $request = new Request(['requestMethod' => 'GET', 'requestPath' => '/health']);

        $this->assertTrue($rule->match($request));
    }
}
