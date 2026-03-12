<?php

namespace Tent\Tests\Configuration;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Configuration;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;

class ConfigurationAddMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::reset();
    }

    public function testAddMiddlewareAddsMiddlewareToExistingRule()
    {
        Configuration::buildRule([
            'name' => 'api-persons',
            'handler' => [
                'class' => '\Tent\Tests\Support\Handlers\RequestToBodyHandler',
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
            ]
        ]);

        $rule = Configuration::getRule('api-persons');
        $handler = $rule->handler();

        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/persons',
        ]);

        $responseBefore = $handler->handleRequest($request);
        $bodyBefore = json_decode($responseBefore->body(), true);
        $this->assertArrayNotHasKey('X-Added', $bodyBefore['headers'] ?? []);

        Configuration::addMiddleware([
            'rule'       => 'api-persons',
            'middleware' => [
                'class'   => SetHeadersMiddleware::class,
                'headers' => ['X-Added' => 'yes'],
            ],
        ]);

        $responseAfter = $handler->handleRequest($request);
        $bodyAfter = json_decode($responseAfter->body(), true);
        $this->assertEquals('yes', $bodyAfter['headers']['X-Added']);
    }

    public function testAddMiddlewareThrowsWhenRuleNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Rule 'non-existent' not found.");

        Configuration::addMiddleware([
            'rule'       => 'non-existent',
            'middleware' => ['class' => SetHeadersMiddleware::class, 'headers' => []],
        ]);
    }

    public function testAddMiddlewareThrowsWhenRuleKeyMissing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Rule '' not found.");

        Configuration::addMiddleware([
            'middleware' => ['class' => SetHeadersMiddleware::class, 'headers' => []],
        ]);
    }

    public function testAddMiddlewareThrowsWhenMiddlewareKeyMissing()
    {
        Configuration::buildRule([
            'name'     => 'api-persons',
            'handler'  => ['type' => 'proxy', 'host' => 'http://api.com'],
            'matchers' => []
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'middleware'");

        Configuration::addMiddleware(['rule' => 'api-persons']);
    }

    public function testAddMiddlewareThrowsWhenMiddlewareIsNotArray()
    {
        Configuration::buildRule([
            'name'     => 'api-persons',
            'handler'  => ['type' => 'proxy', 'host' => 'http://api.com'],
            'matchers' => []
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'middleware'");

        Configuration::addMiddleware([
            'rule'       => 'api-persons',
            'middleware' => 'not-an-array',
        ]);
    }
}
