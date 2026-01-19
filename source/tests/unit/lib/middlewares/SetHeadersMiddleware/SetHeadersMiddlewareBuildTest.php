<?php

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;

class SetHeadersMiddlewareBuildTest extends TestCase
{
    public function testBuildCreatesInstanceWithHeaders()
    {
        $attributes = [
            'headers' => [
                'Host' => 'example.com',
                'X-Test' => 'value',
            ],
        ];
        $middleware = SetHeadersMiddleware::build($attributes);
        $this->assertInstanceOf(SetHeadersMiddleware::class, $middleware);
    }

    public function testBuildAndProcessing()
    {
        $attributes = [
            'headers' => [
                'Host' => 'example.com',
                'X-Test' => 'value',
            ],
        ];
        $middleware = SetHeadersMiddleware::build($attributes);
        $request = new ProcessingRequest([]);
        $modifiedRequest = $middleware->process($request);

        $this->assertEquals('example.com', $modifiedRequest->headers()['Host']);
        $this->assertEquals('value', $modifiedRequest->headers()['X-Test']);
    }
}
