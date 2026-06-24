<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\CacheStalenessMiddleware;

class CacheStalenessMiddlewareBuildTest extends TestCase
{
    public function testBuildReturnsInstance()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
        ]);

        $this->assertInstanceOf(CacheStalenessMiddleware::class, $middleware);
    }

    public function testBuildParsesLocation()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
        ]);

        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('location');
        $prop->setAccessible(true);

        $this->assertEquals('/tmp/cache', $prop->getValue($middleware)->basePath());
    }

    public function testBuildParsesMaxAgeSeconds()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
        ]);

        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('maxAgeSeconds');
        $prop->setAccessible(true);

        $this->assertSame(300, $prop->getValue($middleware));
    }

    public function testBuildParsesSnakeCaseMaxAgeSeconds()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'max_age_seconds' => 120,
        ]);

        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('maxAgeSeconds');
        $prop->setAccessible(true);

        $this->assertSame(120, $prop->getValue($middleware));
    }

    public function testBuildParsesHost()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
        ]);

        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('host');
        $prop->setAccessible(true);

        $this->assertEquals('http://api:80', $prop->getValue($middleware));
    }

    public function testBuildDefaultsMaxAgeSecondsToZero()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
        ]);

        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('maxAgeSeconds');
        $prop->setAccessible(true);

        $this->assertSame(0, $prop->getValue($middleware));
    }
}
