<?php

namespace Tent\Tests\Middlewares\AppendSuffixToPathMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\AppendSuffixToPathMiddleware;
use Tent\Models\ProcessingRequest;

class AppendSuffixToPathMiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesInstanceWithSuffix()
    {
        $middleware = AppendSuffixToPathMiddleware::build(['suffix' => '.json']);
        $this->assertInstanceOf(AppendSuffixToPathMiddleware::class, $middleware);
    }

    public function testBuildAndProcessing()
    {
        $middleware = AppendSuffixToPathMiddleware::build(['suffix' => '.json']);
        $request = new ProcessingRequest(['requestPath' => '/persons/42/photo']);

        $modifiedRequest = $middleware->processRequest($request);
        $this->assertEquals('/persons/42/photo.json', $modifiedRequest->requestPath());
    }

    public function testBuildDefaultsToEmptySuffix()
    {
        $middleware = AppendSuffixToPathMiddleware::build([]);
        $request = new ProcessingRequest(['requestPath' => '/persons/1/photo']);

        $middleware->processRequest($request);
        $this->assertEquals('/persons/1/photo', $request->requestPath());
    }
}
