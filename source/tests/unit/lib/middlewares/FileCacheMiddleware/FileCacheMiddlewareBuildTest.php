<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Cache\QueryRequestHasher;
use Tent\Tests\Support\Cache\DummyRequestHasher;

class FileCacheMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(FileCacheMiddleware::class, $middleware);
    }

    public function testBuildDefaultsRequestHasherToQueryRequestHasherWhenOmitted()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(QueryRequestHasher::class, $this->getRequestHasher($middleware));
    }

    public function testBuildWithRequestHasherAttributeBuildsConfiguredClass()
    {
        $middleware = FileCacheMiddleware::build([
            'location' => '/tmp/cache',
            'request_hasher' => [
                'class' => DummyRequestHasher::class,
            ],
        ]);

        $this->assertInstanceOf(DummyRequestHasher::class, $this->getRequestHasher($middleware));
    }

    public function testBuildDefaultsToEmptyMatchersWhenMatchersNotProvided()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);
        $reflection = new \ReflectionClass($middleware);
        $matchersProp = $reflection->getProperty('matchers');
        $matchersProp->setAccessible(true);
        $matchers = $matchersProp->getValue($middleware);
        $this->assertCount(0, $matchers);
    }

    public function testBuildWithMatchers()
    {
        $middleware = FileCacheMiddleware::build([
            'location' => '/tmp/cache',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [201]
                ]
            ]
        ]);
        $reflection = new \ReflectionClass($middleware);
        $matchersProp = $reflection->getProperty('matchers');
        $matchersProp->setAccessible(true);
        $matchers = $matchersProp->getValue($middleware);
        $this->assertCount(1, $matchers);
        $matcher = $matchers[0];
        $response = new \Tent\Models\Response(['httpCode' => 201]);
        $this->assertTrue($matcher->matchResponse($response));
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testBuildWithRequireCacheHeaderAttributeIsPassedThrough()
    {
        $middleware = FileCacheMiddleware::build([
            'location' => '/tmp/cache',
            'require_cache_header' => 'X-Cache-Allow',
        ]);

        $this->assertSame('X-Cache-Allow', $this->getRequireCacheHeader($middleware));
    }

    public function testBuildDefaultsRequireCacheHeaderToNullWhenOmitted()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertNull($this->getRequireCacheHeader($middleware));
    }

    private function getRequestHasher(FileCacheMiddleware $middleware)
    {
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('requestHasher');
        $property->setAccessible(true);
        return $property->getValue($middleware);
    }

    private function getRequireCacheHeader(FileCacheMiddleware $middleware)
    {
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('requireCacheHeader');
        $property->setAccessible(true);
        return $property->getValue($middleware);
    }
}
