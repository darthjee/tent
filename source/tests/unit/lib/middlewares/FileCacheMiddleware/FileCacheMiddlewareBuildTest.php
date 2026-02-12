<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;

class FileCacheMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(FileCacheMiddleware::class, $middleware);
    }

    public function testBuildDefaultsTo200WhenHttpCodesNotProvided()
    {
        $middleware = FileCacheMiddleware::build(['location' => '/tmp/cache']);
        $reflection = new \ReflectionClass($middleware);
        $matchersProp = $reflection->getProperty('matchers');
        $matchersProp->setAccessible(true);
        $matchers = $matchersProp->getValue($middleware);
        $this->assertCount(1, $matchers);
        $matcher = $matchers[0];
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertTrue($matcher->match($response));
        $response = new \Tent\Models\Response(['httpCode' => 201]);
        $this->assertFalse($matcher->match($response));
    }

    public function testBuildWithMatchersOverridesHttpCodes()
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
        $this->assertTrue($matcher->match($response));
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertFalse($matcher->match($response));
    }
}
