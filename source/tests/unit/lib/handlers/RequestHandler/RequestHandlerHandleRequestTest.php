<?php

namespace Tent\Tests\Handlers\RequestHandler;

require_once __DIR__ . '/../../../../support/handlers/RequestToBodyHandler.php';
require_once __DIR__ . '/../../../../support/middlewares/QuickResponseMiddleware.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;
use Tent\Models\Response;
use Tent\Tests\Support\Middlewares\QuickResponseMiddleware;

class RequestHandlerHandleRequestTest extends TestCase
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
            'headers' => null,
            'body' => null,
        ];
        $actual = json_decode($response->body(), true);
        $this->assertEquals($expected, $actual);
    }

    public function testQuickResponseMiddleware()
    {
        $handler = new RequestToBodyHandler();
        $attributes = [
            'class' => QuickResponseMiddleware::class,
        ];
        $middleware = $handler->buildRequestMiddleware($attributes);
        $this->assertInstanceOf(QuickResponseMiddleware::class, $middleware);

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('Quick Response', $response->body());
    }
}
