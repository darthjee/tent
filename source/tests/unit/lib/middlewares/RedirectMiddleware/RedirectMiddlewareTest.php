<?php

namespace Tent\Tests\Middlewares\RedirectMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\RedirectMiddleware;
use Tent\Models\ProcessingRequest;

class RedirectMiddlewareTest extends TestCase
{
    public function testBuildThrowsOnMissingPattern()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required redirect pattern.');

        RedirectMiddleware::build([
            'replacement' => '/new',
        ]);
    }

    public function testBuildThrowsOnMissingReplacement()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required redirect replacement.');

        RedirectMiddleware::build([
            'pattern' => '/^\/old$/',
        ]);
    }

    public function testBuildThrowsOnInvalidPattern()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid redirect pattern '/(invalid/'.");

        RedirectMiddleware::build([
            'pattern' => '/(invalid/',
            'replacement' => '/new',
        ]);
    }

    public function testProcessRequestCreatesRedirectResponse()
    {
        $middleware = RedirectMiddleware::build([
            'pattern' => '/^\/old\/(.*)$/',
            'replacement' => '/new/$1',
        ]);
        $request = new ProcessingRequest([
            'requestPath' => '/old/path',
            'query' => 'a=1',
        ]);

        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertTrue($result->hasResponse());
        $this->assertEquals(302, $result->response()->httpCode());
        $this->assertEquals(['Location: /new/path?a=1'], $result->response()->headers());
    }

    public function testProcessRequestDoesNothingWhenPatternDoesNotMatch()
    {
        $middleware = RedirectMiddleware::build([
            'pattern' => '/^\/old\/(.*)$/',
            'replacement' => '/new/$1',
        ]);
        $request = new ProcessingRequest([
            'requestPath' => '/current/path',
            'query' => 'a=1',
        ]);

        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertFalse($result->hasResponse());
    }
}
