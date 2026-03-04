<?php

namespace Tent\Tests\Middlewares\RenameHeaderMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\RenameHeaderMiddleware;
use Tent\Models\ProcessingRequest;

class RenameHeaderMiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesInstance()
    {
        $middleware = RenameHeaderMiddleware::build([
            'from' => 'Host',
            'to'   => 'X-Original-Host',
        ]);

        $this->assertInstanceOf(RenameHeaderMiddleware::class, $middleware);
    }

    public function testBuildAndProcessing()
    {
        $middleware = RenameHeaderMiddleware::build([
            'from' => 'Host',
            'to'   => 'X-Original-Host',
        ]);

        $request = new ProcessingRequest(['headers' => ['Host' => 'example.com']]);
        $modifiedRequest = $middleware->processRequest($request);

        $this->assertEquals('example.com', $modifiedRequest->headers()['X-Original-Host']);
        $this->assertArrayNotHasKey('Host', $modifiedRequest->headers());
    }
}
