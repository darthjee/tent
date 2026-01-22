<?php

namespace Tent\Tests\Handlers\RequestHandler;

require_once __DIR__ . '/../../../../support/handlers/RequestToBodyHandler.php';
require_once __DIR__ . '/../../../../support/middlewares/DummyRequestMiddleware.php';

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

    public function testBuildWithTypeProxy()
    {
        $handler = RequestHandler::build([
            'type' => 'proxy',
            'host' => 'http://api.com'
        ]);

        $this->assertInstanceOf(\Tent\Handlers\ProxyRequestHandler::class, $handler);
    }

    public function testBuildWithTypeStatic()
    {
        $handler = RequestHandler::build([
            'type' => 'static',
            'filePath' => '/path/to/file.txt',
        ]);

        $this->assertInstanceOf(\Tent\Handlers\StaticFileHandler::class, $handler);
    }

    public function testBuildWithUnknownTypeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown handler type: unknown');

        RequestHandler::build([
            'type' => 'unknown',
        ]);
    }

    public function testBuildWithMissingTypeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing handler type');

        RequestHandler::build([]);
    }
}
