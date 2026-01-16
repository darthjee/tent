<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\ProxyRequestHandler;

class ProxyRequestHandlerBuildTest extends TestCase
{
    public function testBuildCreatesProxyRequestHandlerWithHost()
    {
        $handler = ProxyRequestHandler::build(['host' => 'http://api.com']);
        $this->assertInstanceOf(ProxyRequestHandler::class, $handler);

        // Reflection to check if the server property is set correctly
        $reflection = new \ReflectionClass($handler);
        $serverProp = $reflection->getProperty('server');
        $serverProp->setAccessible(true);
        $server = $serverProp->getValue($handler);
        $this->assertEquals('http://api.com', $server->targetHost());
    }
}
