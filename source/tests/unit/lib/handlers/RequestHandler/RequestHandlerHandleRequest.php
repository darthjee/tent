<?php

namespace Tent\Tests\Handlers\RequestHandler;


class RequestHandlerBuildRequestMiddlewareTest extends TestCase
{
    public function testRegularRequestHandler()
    {
        $handler = new RequestToBodyHandler();

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $expected = [
            'uri' => null,
            'query' => null,
            'method' => null,
            'headers' => ['X-Test' => 'value'],
            'body' => null,
        ];
        $actual = json_decode($response->body(), true);
        $this->assertEquals($expected, $actual);
    }
}