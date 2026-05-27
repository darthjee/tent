<?php

namespace Tent\Tests\RequestHandlers\MissingRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Models\ProcessingRequest;
use Tent\RequestHandlers\MissingRequestHandler;
use Tent\Models\Request;

class MissingRequestHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testReturns404AndLogsWhy(): void
    {
        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->once())
            ->method('log')
            ->with('[404] - no rules matched — method: GET, uri: /missing', 'debug');
        Logger::setInstance($instance);

        $handler = new MissingRequestHandler();
        $request = new Request([
            'requestMethod' => 'GET',
            'requestPath' => '/missing'
        ]);
        $response = $handler->handleRequest(new ProcessingRequest(['request' => $request]));

        $this->assertEquals(404, $response->httpCode());
    }
}
