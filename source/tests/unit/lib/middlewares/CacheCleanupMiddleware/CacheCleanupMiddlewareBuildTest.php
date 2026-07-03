<?php

namespace Tent\Tests\Middlewares\CacheCleanupMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\CacheCleanupMiddleware;

class CacheCleanupMiddlewareBuildTest extends TestCase
{
    public function testBuildWithLocationAttribute()
    {
        $middleware = CacheCleanupMiddleware::build(['location' => '/tmp/cache']);

        $this->assertInstanceOf(CacheCleanupMiddleware::class, $middleware);
    }

    public function testBuildWithoutClearUsesNullTargets()
    {
        $middleware = CacheCleanupMiddleware::build(['location' => '/tmp/cache']);
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('clearTargets');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($middleware));
    }

    public function testBuildWithClearAttributeStoresTargets()
    {
        $middleware = CacheCleanupMiddleware::build([
            'location' => '/tmp/cache',
            'clear'    => ['collection', 'entity'],
        ]);
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('clearTargets');
        $prop->setAccessible(true);

        $this->assertSame(['collection', 'entity'], $prop->getValue($middleware));
    }

    public function testBuildWithSingleClearTargetCoercesToArray()
    {
        $middleware = CacheCleanupMiddleware::build([
            'location' => '/tmp/cache',
            'clear'    => 'collection',
        ]);
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('clearTargets');
        $prop->setAccessible(true);

        $this->assertSame(['collection'], $prop->getValue($middleware));
    }

    public function testBuildWithoutCustomDefaultsToEmptyArray()
    {
        $middleware = CacheCleanupMiddleware::build(['location' => '/tmp/cache']);
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('customRules');
        $prop->setAccessible(true);

        $this->assertSame([], $prop->getValue($middleware));
    }

    public function testBuildWithCustomAttributeStoresRules()
    {
        $custom = [
            '/games/:game_slug/photo_upload' => [
                '/games.json',
                '/games/:game_slug.json',
            ],
        ];

        $middleware = CacheCleanupMiddleware::build([
            'location' => '/tmp/cache',
            'custom'   => $custom,
        ]);
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('customRules');
        $prop->setAccessible(true);

        $this->assertSame($custom, $prop->getValue($middleware));
    }
}
