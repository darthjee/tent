<?php

namespace Tent\Tests\RequestHandlers\ProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\ProxyRequestHandler;

class ProxyRequestHandlerBuildTest extends TestCase
{
    public function testBuildCreatesProxyRequestHandlerWithHost()
    {
        $handler = ProxyRequestHandler::build(['host' => 'http://api.com']);
        $this->assertInstanceOf(ProxyRequestHandler::class, $handler);
    }

    public function testBuildAcceptsValidTimeouts()
    {
        $handler = ProxyRequestHandler::build(['host' => 'http://api.com', 'connect_timeout' => 0, 'timeout' => 10]);
        $this->assertInstanceOf(ProxyRequestHandler::class, $handler);
    }

    /**
     * @dataProvider invalidTimeoutProvider
     */
    public function testBuildRejectsInvalidTimeouts(string $key, $value)
    {
        $this->expectException(\InvalidArgumentException::class);
        ProxyRequestHandler::build(['host' => 'http://api.com', $key => $value]);
    }

    public static function invalidTimeoutProvider(): array
    {
        $cases = [];
        foreach (['connect_timeout', 'timeout'] as $key) {
            foreach ([-1, 'abc', 2.5] as $value) {
                $cases["$key " . var_export($value, true)] = [$key, $value];
            }
        }
        return $cases;
    }
}
