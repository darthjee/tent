<?php

namespace Tent\Tests\Configuration;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Configuration;
use Tent\Models\Request;

class ConfigurationAddMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::reset();
    }

    public function testAddMatcherAddsMatcherToExistingRule()
    {
        Configuration::buildRule([
            'name' => 'api-persons',
            'handler' => [
                'type' => 'proxy',
                'host' => 'http://api.com'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
            ]
        ]);

        $request = new Request([
            'requestMethod' => 'POST',
            'requestPath' => '/persons',
        ]);

        $rule = Configuration::getRule('api-persons');
        $this->assertFalse($rule->match($request));

        Configuration::addMatcher([
            'rule'    => 'api-persons',
            'matcher' => ['method' => 'POST', 'uri' => '/persons', 'type' => 'exact'],
        ]);

        $this->assertTrue($rule->match($request));
    }

    public function testAddMatcherThrowsWhenRuleNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Rule 'non-existent' not found.");

        Configuration::addMatcher([
            'rule'    => 'non-existent',
            'matcher' => ['method' => 'GET', 'uri' => '/test', 'type' => 'exact'],
        ]);
    }

    public function testAddMatcherThrowsWhenRuleKeyMissing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'rule'");

        Configuration::addMatcher([
            'matcher' => ['method' => 'GET', 'uri' => '/test', 'type' => 'exact'],
        ]);
    }

    public function testAddMatcherThrowsWhenMatcherKeyMissing()
    {
        Configuration::buildRule([
            'name' => 'api-persons',
            'handler' => ['type' => 'proxy', 'host' => 'http://api.com'],
            'matchers' => []
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'matcher'");

        Configuration::addMatcher(['rule' => 'api-persons']);
    }

    public function testAddMatcherThrowsWhenMatcherIsNotArray()
    {
        Configuration::buildRule([
            'name' => 'api-persons',
            'handler' => ['type' => 'proxy', 'host' => 'http://api.com'],
            'matchers' => []
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'matcher'");

        Configuration::addMatcher([
            'rule'    => 'api-persons',
            'matcher' => 'not-an-array',
        ]);
    }
}
