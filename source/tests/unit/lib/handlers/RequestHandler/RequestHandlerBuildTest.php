<?php

namespace Tent\Tests\Handlers\RequestHandler;

require_once __DIR__ . '/../../../../support/handlers/RequestToBodyHandler.php';
require_once __DIR__ . '/../../../../support/middlewares/DummyMiddleware.php';

use PHPUnit\Framework\TestCase;
use Tent\Handlers\RequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Handlers\RequestToBodyHandler;

class RequestHandlerBuildTest extends TestCase
{
    public function testBuildWithClass()
    {
        $handler = RequestHandler::build([
            'class' => \Tent\Tests\Support\Handlers\RequestToBodyHandler::class,
        ]);

        $this->assertInstanceOf(\Tent\Tests\Support\Handlers\RequestToBodyHandler::class, $handler);
    }

    public function testBuildWithClassName()
    {
        $handler = RequestHandler::build([
            'class' => "\Tent\Tests\Support\Handlers\RequestToBodyHandler",
        ]);

        $this->assertInstanceOf(\Tent\Tests\Support\Handlers\RequestToBodyHandler::class, $handler);
    }
}
