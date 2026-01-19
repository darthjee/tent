<?php

namespace Tent\Tests\Handlers\RequestHandler;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\RequestHandler;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Middlewares\RequestMiddleware;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;
use Tent\Models\ProcessingRequest;

class RequestHandlerBuildRequestMiddlewareTest extends TestCase
{
    public function testBuildRequestMiddlewareAddsMiddlewareToHandler()
    {
        $handler = new RequestToBodyHandler();
        $attributes = [
            'class' => SetHeadersMiddleware::class,
            'headers' => ['X-Test' => 'value'],
        ];
        $middleware = $handler->buildRequestMiddleware($attributes);
        $this->assertInstanceOf(SetHeadersMiddleware::class, $middleware);

        $request = new ProcessingRequest([]);
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

    public function testBuildRequestMiddlewaresAddsMultipleMiddlewares()
    {
        $handler = new RequestToBodyHandler();
        $middlewaresAttributes = [
            [
                'class' => SetHeadersMiddleware::class,
                'headers' => ['X-Test' => 'value'],
            ],
            [
                'class' => SetHeadersMiddleware::class,
                'headers' => ['Host' => 'example.com'],
            ],
        ];
        $middlewares = $handler->buildRequestMiddlewares($middlewaresAttributes);
        $this->assertCount(2, $middlewares);
        foreach ($middlewares as $middleware) {
            $this->assertInstanceOf(SetHeadersMiddleware::class, $middleware);
        }

        $request = new ProcessingRequest([]);
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
