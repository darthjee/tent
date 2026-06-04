<?php

namespace Tent\Tests\RequestHandlers\RequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';


use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;
use Tent\Models\Response;
use Tent\Middlewares\RedirectMiddleware;
use Tent\Tests\Support\Middlewares\QuickResponseMiddleware;
use Tent\Tests\Support\Middlewares\DummyResponseMiddleware;

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
            'query' => '',
            'method' => null,
            'headers' => [],
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
        $middleware = $handler->buildMiddleware($attributes);
        $this->assertInstanceOf(QuickResponseMiddleware::class, $middleware);

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('Quick Response', $response->body());
    }

    public function testResponseMiddleware()
    {
        $handler = new RequestToBodyHandler();
        $attributes = [
            'class' => DummyResponseMiddleware::class,
        ];
        $middleware = $handler->buildMiddleware($attributes);
        $this->assertInstanceOf(DummyResponseMiddleware::class, $middleware);

        $request = new ProcessingRequest();
        $response = $handler->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('Dummy response body', $response->body());
    }

    public function testRedirectMiddlewareShortCircuitsHandlerExecution()
    {
        $handler = new RequestToBodyHandler();
        $handler->buildMiddleware([
            'class' => RedirectMiddleware::class,
            'pattern' => '/^\/old\/(.*)$/',
            'replacement' => '/new/$1',
        ]);

        $request = new ProcessingRequest([
            'requestPath' => '/old/path',
            'query' => 'x=1',
        ]);
        $response = $handler->handleRequest($request);

        $this->assertSame('', $response->body());
        $this->assertEquals(302, $response->httpCode());
        $this->assertEquals(['Location: /new/path?x=1'], $response->headers());
    }
}
