<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\Rule;

class RuleBuildTest extends TestCase
{
    public function testBuildCreatesRuleWithNamedParameters()
    {
        $rule = Rule::build([
            'host' => 'http://api.com',
            'rules' => [
                ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
                ['method' => 'POST', 'uri' => '/submit', 'type' => 'begins_with']
            ]
        ]);

        $this->assertInstanceOf(Rule::class, $rule);
        $handler = $rule->handler();
        $this->assertInstanceOf(\Tent\Handlers\ProxyRequestHandler::class, $handler);

        $requestGet = $this->createMock(\Tent\Models\Request::class);
        $requestGet->method('requestMethod')->willReturn('GET');
        $requestGet->method('requestUrl')->willReturn('/index.html');

        $requestPost = $this->createMock(\Tent\Models\Request::class);
        $requestPost->method('requestMethod')->willReturn('POST');
        $requestPost->method('requestUrl')->willReturn('/submit/123');

        $this->assertTrue($rule->match($requestGet));
        $this->assertTrue($rule->match($requestPost));
    }
}
