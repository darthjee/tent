<?php

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;

class SetHeadersMiddlewareTest extends TestCase
{
    public function testProcessSetsHeadersOnProcessingRequest()
    {
        $expectedHeaders = [
            'Host' => 'some_host',
            'X-Test' => 'value',
        ];
        $request = new ProcessingRequest([
            'headers' => $expectedHeaders
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
