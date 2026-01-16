<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Configuration;
use Tent\Models\Rule;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Models\Request;

class ConfigurationBuildRuleTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset rules before each test
        Configuration::reset();
    }

    public function testBuildRuleCreatesRuleWithProxyHandler()
    {
        $rule = Configuration::buildRule([
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

        $requestGet = new Request([
            'requestMethod' => 'GET',
            'requestUrl' => '/index.html',
        ]);

        $requestPost = new Request([
            'requestMethod' => 'POST',
            'requestUrl' => '/submit/123',
        ]);

        $this->assertTrue($rule->match($requestGet));
        $this->assertTrue($rule->match($requestPost));
    }
}
