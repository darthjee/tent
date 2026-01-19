<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\Rule;
use Tent\Models\ProcessingRequest;
use Tent\Handlers\ProxyRequestHandler;

class RuleBuildTest extends TestCase
{
    public function testBuildCreatesRuleWithProxyHandler()
    {
        $rule = Rule::build([
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
                ['method' => 'POST', 'uri' => '/submit', 'type' => 'begins_with']
            ]
        ]);

        $this->assertInstanceOf(Rule::class, $rule);
        $handler = $rule->handler();
        $this->assertInstanceOf(ProxyRequestHandler::class, $handler);

        $requestGet = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestUrl' => '/index.html',
        ]);

        $requestPost = new ProcessingRequest([
            'requestMethod' => 'POST',
            'requestUrl' => '/submit/123',
        ]);

        $this->assertTrue($rule->match($requestGet));
        $this->assertTrue($rule->match($requestPost));
    }

    public function testBuildCreatesRuleWithMiddleware()
    {
        $rule = Rule::build([
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'middlewares' => [
                [
                    'class' => '\Tent\Tests\Support\Middlewares\DummyMiddleware',
                ]
            ]
        ]);

    }
}
