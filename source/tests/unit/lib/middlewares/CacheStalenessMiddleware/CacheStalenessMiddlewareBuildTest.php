<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\AllowedHeaderFilter;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;

class CacheStalenessMiddlewareBuildTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

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

    public function testBuildDefaultsToDenyModeWithDefaultExcludedHeadersWhenOmitted()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
        ]);

        $filter = $this->getHeaderFilter($middleware);
        $this->assertInstanceOf(ExcludedHeaderFilter::class, $filter);

        $response = new \Tent\Models\Response(['headers' => ['Set-Cookie: a=1', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildWithExcludedHeadersAttributeOverridesDefaultList()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'excluded_headers' => ['X-Custom'],
        ]);

        $filter = $this->getHeaderFilter($middleware);
        $response = new \Tent\Models\Response([
            'headers' => ['Set-Cookie: a=1', 'X-Custom: 1', 'Content-Type: text/plain'],
        ]);
        $this->assertEquals(
            ['Set-Cookie: a=1', 'Content-Type: text/plain'],
            $filter->filter($response)->headers()
        );
    }

    public function testBuildWithAdditionalExcludedHeadersAttributeMergesOnTopOfDefaults()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'additional_excluded_headers' => ['X-Custom'],
        ]);

        $filter = $this->getHeaderFilter($middleware);
        $response = new \Tent\Models\Response([
            'headers' => ['Set-Cookie: a=1', 'X-Custom: 1', 'Content-Type: text/plain'],
        ]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildWithAllowModeAndAllowedHeadersAttribute()
    {
        $middleware = CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'mode' => 'allow',
            'allowed_headers' => ['Content-Type'],
        ]);

        $filter = $this->getHeaderFilter($middleware);
        $this->assertInstanceOf(AllowedHeaderFilter::class, $filter);

        $response = new \Tent\Models\Response(['headers' => ['Set-Cookie: a=1', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildWithAllowModeAndMissingAllowedHeadersThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'mode' => 'allow',
        ]);
    }

    public function testBuildWithInvalidModeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        CacheStalenessMiddleware::build([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'mode' => 'invalid',
        ]);
    }

    private function getHeaderFilter(CacheStalenessMiddleware $middleware)
    {
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headerFilter');
        $property->setAccessible(true);
        return $property->getValue($middleware);
    }
}
