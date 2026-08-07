<?php

namespace Tent\Tests\Middlewares\FilterQueryParamsMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FilterQueryParamsMiddleware;
use Tent\Models\ProcessingRequest;

class FilterQueryParamsMiddlewareTest extends TestCase
{
    public function testAllowModeKeepsOnlyListedParams()
    {
        $request = new ProcessingRequest(['query' => 'id=1&page=2&secret=3']);

        $middleware = new FilterQueryParamsMiddleware(['id', 'page'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertSame('id=1&page=2', $result->query());
    }

    public function testDenyModeStripsListedParams()
    {
        $request = new ProcessingRequest(['query' => 'id=1&page=2&secret=3']);

        $middleware = new FilterQueryParamsMiddleware(['secret'], 'deny');
        $result = $middleware->processRequest($request);

        $this->assertSame('id=1&page=2', $result->query());
    }

    public function testEmptyQueryIsNoOp()
    {
        $request = new ProcessingRequest(['query' => '']);

        $middleware = new FilterQueryParamsMiddleware(['id'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertSame('', $result->query());
    }

    public function testEmptyParamsListInAllowModeRemovesEverything()
    {
        $request = new ProcessingRequest(['query' => 'id=1&page=2']);

        $middleware = new FilterQueryParamsMiddleware([], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('', $result->query());
    }

    public function testEmptyParamsListInDenyModeKeepsEverything()
    {
        $request = new ProcessingRequest(['query' => 'id=1&page=2']);

        $middleware = new FilterQueryParamsMiddleware([], 'deny');
        $result = $middleware->processRequest($request);

        $this->assertSame('id=1&page=2', $result->query());
    }

    public function testDuplicateScalarKeysCollapsePerParseStr()
    {
        $request = new ProcessingRequest(['query' => 'a=1&a=2']);

        $middleware = new FilterQueryParamsMiddleware(['a'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('a=2', $result->query());
    }

    public function testArrayStyleKeysMatchedByTopLevelKeyOnlyWithBrackets()
    {
        $request = new ProcessingRequest(['query' => 'a[]=1&a[]=2&b=3']);

        $middleware = new FilterQueryParamsMiddleware(['a'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('a%5B0%5D=1&a%5B1%5D=2', $result->query());
    }

    public function testArrayStyleKeysMatchedByTopLevelKeyOnlyWithNestedKey()
    {
        $request = new ProcessingRequest(['query' => 'a[b]=1&c=2']);

        $middleware = new FilterQueryParamsMiddleware(['a'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('a%5Bb%5D=1', $result->query());
    }

    public function testParamsAbsentFromQueryHaveNoEffect()
    {
        $request = new ProcessingRequest(['query' => 'id=1']);

        $middleware = new FilterQueryParamsMiddleware(['id', 'missing'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('id=1', $result->query());
    }

    public function testOrderOfSurvivingParamsIsPreserved()
    {
        $request = new ProcessingRequest(['query' => 'z=1&a=2&m=3&b=4']);

        $middleware = new FilterQueryParamsMiddleware(['b', 'z', 'a'], 'allow');
        $result = $middleware->processRequest($request);

        $this->assertSame('z=1&a=2&b=4', $result->query());
    }
}
