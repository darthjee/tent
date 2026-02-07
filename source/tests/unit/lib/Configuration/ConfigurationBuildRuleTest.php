<?php

namespace Tent\Tests;

require_once __DIR__ . '/../../../../source/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Configuration;
use Tent\Models\Rule;
use Tent\RequestHandlers\ProxyRequestHandler;
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
            'requestPath' => '/index.html',
        ]);

        $requestPost = new Request([
            'requestMethod' => 'POST',
            'requestPath' => '/submit/123',
        ]);

        $this->assertTrue($rule->match($requestGet));
        $this->assertTrue($rule->match($requestPost));
        // Verifica se Configuration::getRules() retorna a nova Rule
        $rules = Configuration::getRules();
        $this->assertNotEmpty($rules);
        $this->assertInstanceOf(Rule::class, $rules[0]);
        $this->assertInstanceOf(ProxyRequestHandler::class, $rules[0]->handler());
    }
}
