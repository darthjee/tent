<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

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
        $filtersProp = $reflection->getProperty('filters');
        $filtersProp->setAccessible(true);
        $filters = $filtersProp->getValue($middleware);
        $this->assertCount(1, $filters);
        $filter = $filters[0];
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertTrue($filter->matchResponse($response));
        $response = new \Tent\Models\Response(['httpCode' => 201]);
        $this->assertFalse($filter->matchResponse($response));
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
        $filtersProp = $reflection->getProperty('filters');
        $filtersProp->setAccessible(true);
        $filters = $filtersProp->getValue($middleware);
        $this->assertCount(1, $filters);
        $filter = $filters[0];
        $response = new \Tent\Models\Response(['httpCode' => 201]);
        $this->assertTrue($filter->matchResponse($response));
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertFalse($filter->matchResponse($response));
    }
}
