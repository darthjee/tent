<?php

namespace Tent\Tests\Middlewares\FilterQueryParamsMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FilterQueryParamsMiddleware;
use Tent\Models\ProcessingRequest;

class FilterQueryParamsMiddlewareBuildTest extends TestCase
{
    public function testBuildDefaultsParamsToEmptyArray()
    {
        $middleware = FilterQueryParamsMiddleware::build([]);

        $request = new ProcessingRequest(['query' => 'a=1&b=2']);
        $result = $middleware->processRequest($request);

        // default mode is 'allow' with no params, everything is stripped
        $this->assertSame('', $result->query());
    }

    public function testBuildDefaultsModeToAllow()
    {
        $middleware = FilterQueryParamsMiddleware::build(['params' => ['a']]);

        $request = new ProcessingRequest(['query' => 'a=1&b=2']);
        $result = $middleware->processRequest($request);

        $this->assertSame('a=1', $result->query());
    }

    public function testBuildAcceptsExplicitAllowMode()
    {
        $middleware = FilterQueryParamsMiddleware::build([
            'params' => ['a'],
            'mode' => 'allow',
        ]);

        $request = new ProcessingRequest(['query' => 'a=1&b=2']);
        $result = $middleware->processRequest($request);

        $this->assertSame('a=1', $result->query());
    }

    public function testBuildAcceptsExplicitDenyMode()
    {
        $middleware = FilterQueryParamsMiddleware::build([
            'params' => ['a'],
            'mode' => 'deny',
        ]);

        $request = new ProcessingRequest(['query' => 'a=1&b=2']);
        $result = $middleware->processRequest($request);

        $this->assertSame('b=2', $result->query());
    }

    public function testBuildThrowsForInvalidMode()
    {
        $this->expectException(\InvalidArgumentException::class);

        FilterQueryParamsMiddleware::build([
            'params' => ['a'],
            'mode' => 'invalid',
        ]);
    }
}
