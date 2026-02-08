<?php

namespace Tent\Tests\RequestHandlers\RequestHandler;

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\RequestHandler;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;
use Tent\Models\ProcessingRequest;

class RequestHandlerBuildMiddlewareTest extends TestCase
{
    public function testBuildMiddlewareAddsMiddlewareToHandler()
    {
        $handler = new RequestToBodyHandler();
        $attributes = [
            'class' => SetHeadersMiddleware::class,
            'headers' => ['X-Test' => 'value'],
        ];
        $middleware = $handler->buildMiddleware($attributes);
        $this->assertInstanceOf(SetHeadersMiddleware::class, $middleware);

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

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

    public function testBuildMiddlewaresAddsMultipleMiddlewares()
    {
        $handler = new RequestToBodyHandler();
        $attributes = [
            [
                'class' => SetHeadersMiddleware::class,
                'headers' => ['X-Test' => 'value'],
            ],
            [
                'class' => SetHeadersMiddleware::class,
                'headers' => ['Host' => 'example.com'],
            ],
        ];
        $middlewares = $handler->buildMiddlewares($attributes);
        $this->assertCount(2, $middlewares);
        foreach ($middlewares as $middleware) {
            $this->assertInstanceOf(SetHeadersMiddleware::class, $middleware);
        }

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

        $expected = [
            'uri' => null,
            'query' => null,
            'method' => null,
            'headers' => ['X-Test' => 'value', 'Host' => 'example.com'],
            'body' => null,
        ];
        $actual = json_decode($response->body(), true);
        $this->assertEquals($expected, $actual);
    }
}
