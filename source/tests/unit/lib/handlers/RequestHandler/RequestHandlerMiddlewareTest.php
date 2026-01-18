<?php

namespace Tent\Tests\Handlers\RequestHandler;

require_once __DIR__ . '/../../../../support/handlers/RequestToBodyHandler.php';
require_once __DIR__ . '/../../../../support/middlewares/DummyMiddleware.php';

use PHPUnit\Framework\TestCase;
use Tent\Handlers\RequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Middlewares\RequestMiddleware;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;
use Tent\Tests\Support\Middlewares\DummyMiddleware;

//require_once __DIR__ . '/../../../../../tests/support/middlewares/DummyMiddleware.php';

class RequestHandlerMiddlewareTest extends TestCase
{
    public function testAddMiddlewareAndApplyMiddlewares()
    {
        $handler = new RequestToBodyHandler();
        $middleware = new DummyMiddleware();
        $handler->addMiddleware($middleware);

        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestUrl' => '/test',
            'headers' => [
                'Accept' => 'application/json',
            ]
        ]);

        $response = $handler->handleRequest($request);
        $expected = [
            'uri' => '/test',
            'query' => null,
            'method' => 'GET',
            'body' => null,
            'headers' => [
                'X-Test' => 'middleware',
                'Accept' => 'application/json',
            ],
        ];
        $actual = json_decode($response->body(), true);
        $this->assertEquals($expected, $actual);
    }
}
