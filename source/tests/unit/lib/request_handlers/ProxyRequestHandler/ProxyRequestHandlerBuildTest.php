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
}
