<?php

namespace Tent\Tests\Middlewares\AppendSuffixToPathMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\AppendSuffixToPathMiddleware;
use Tent\Models\ProcessingRequest;

class AppendSuffixToPathMiddlewareTest extends TestCase
{
    public function testAppendsSuffixToPath()
    {
        $request = new ProcessingRequest(['requestPath' => '/persons/1/photo']);
        $middleware = new AppendSuffixToPathMiddleware('.json');

        $result = $middleware->processRequest($request);
        $this->assertSame($request, $result);
        $this->assertEquals('/persons/1/photo.json', $result->requestPath());
    }

    public function testAppendsSuffixToPathWithTrailingSegment()
    {
        $request = new ProcessingRequest(['requestPath' => '/api/resource']);
        $middleware = new AppendSuffixToPathMiddleware('.json');

        $middleware->processRequest($request);
        $this->assertEquals('/api/resource.json', $request->requestPath());
    }

    public function testEmptySuffixLeavesPathUnchanged()
    {
        $request = new ProcessingRequest(['requestPath' => '/persons/1/photo']);
        $middleware = new AppendSuffixToPathMiddleware('');

        $middleware->processRequest($request);
        $this->assertEquals('/persons/1/photo', $request->requestPath());
    }

}
