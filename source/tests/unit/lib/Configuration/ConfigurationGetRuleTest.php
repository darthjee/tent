<?php

namespace Tent\Tests\Configuration;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Configuration;
use Tent\Models\Rule;

class ConfigurationGetRuleTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::reset();
    }

    public function testGetRuleReturnsRuleByName()
    {
        Configuration::buildRule([
            'name' => 'api-index',
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact']
            ]
        ]);
        Configuration::buildRule([
            'name' => 'api-users',
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/users', 'type' => 'exact']
            ]
        ]);

        $found = Configuration::getRule('api-users');
        $this->assertInstanceOf(Rule::class, $found);
        $this->assertEquals('api-users', $found->name());
    }

    public function testGetRuleReturnsNullIfNotFound()
    {
        Configuration::buildRule([
            'name' => 'api-index',
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact']
            ]
        ]);
        $this->assertNull(Configuration::getRule('not-exist'));
    }
}
