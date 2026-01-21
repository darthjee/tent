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
            'requestPath' => '/index.html',
        ]);

        $requestPost = new ProcessingRequest([
            'requestMethod' => 'POST',
            'requestPath' => '/submit/123',
        ]);

        $this->assertTrue($rule->match($requestGet));
        $this->assertTrue($rule->match($requestPost));
    }

    public function testBuildCreatesRuleWithMiddleware()
    {
        $rule = Rule::build([
            'handler' => [
                'class' => '\Tent\Tests\Support\Handlers\RequestToBodyHandler',
            ],
            'middlewares' => [
                [
                    'class' => '\Tent\Tests\Support\Middlewares\DummyMiddleware',
                ]
            ]
        ]);

        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/index.html',
        ]);

        $handler = $rule->handler();

        $response = $handler->handleRequest($request);

        $expected = [
            'uri' => '/index.html',
            'query' => null,
            'method' => 'GET',
            'headers' => ['X-Test' => 'middleware'],
            'body' => null,
        ];
        $actual = json_decode($response->body(), true);
        $this->assertEquals($expected, $actual);
    }
}
