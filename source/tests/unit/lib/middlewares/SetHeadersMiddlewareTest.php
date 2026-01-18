<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;

class SetHeadersMiddlewareTest extends TestCase
{
    public function testOverridingHeaders()
    {
        $expectedHeaders = [
            'Host' => 'some_host',
            'X-Test' => 'value',
            'User-Agent' => 'PHPUnit',
        ];
        $requestHeaders = [
            'Host' => 'original_host',
            'User-Agent' => 'PHPUnit',
        ];

        $request = new ProcessingRequest([
            'headers' => $requestHeaders
        ]);

        $middleware = new SetHeadersMiddleware([
            'Host' => 'some_host',
            'X-Test' => 'value',
        ]);

        $result = $middleware->process($request);
        $this->assertSame($request, $result);
        $this->assertEquals($expectedHeaders, $result->headers());
    }
}
