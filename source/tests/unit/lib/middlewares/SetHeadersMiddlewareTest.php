<?php

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Models\ProcessingRequest;

class SetHeadersMiddlewareTest extends TestCase
{
    public function testProcessSetsHeadersOnProcessingRequest()
    {
        $request = $this->getMockBuilder(ProcessingRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader'])
            ->getMock();

        $request->expects($this->exactly(2))
            ->method('setHeader')
            ->withConsecutive(
                ['Host', 'some_host'],
                ['X-Test', 'value']
            );

        $middleware = new SetHeadersMiddleware([
            'Host' => 'some_host',
            'X-Test' => 'value',
        ]);

        $result = $middleware->process($request);
        $this->assertSame($request, $result);
    }

    public function testProcessWithEmptyHeadersDoesNothing()
    {
        $request = $this->getMockBuilder(ProcessingRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader'])
            ->getMock();

        $request->expects($this->never())->method('setHeader');

        $middleware = new SetHeadersMiddleware([]);
        $result = $middleware->process($request);
        $this->assertSame($request, $result);
    }
}
