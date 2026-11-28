<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Tests\Support\Log\CapturingLoggerInstance;

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

    /**
     * @dataProvider deprecatedAttributes
     */
    public function testBuildAcceptsDeprecatedAttributeAndLogsWarning(string $name, mixed $value)
    {
        $logger = $this->captureLogs();

        $middleware = CacheStalenessMiddleware::build($this->attributes([$name => $value]));

        $this->assertInstanceOf(CacheStalenessMiddleware::class, $middleware);
        $this->assertSame([['warn', $this->deprecationMessage($name)]], $logger->lines);
    }

    public static function deprecatedAttributes(): array
    {
        return [
            'mode' => ['mode', 'allow'],
            'excluded_headers' => ['excluded_headers', ['X-Custom']],
            'additional_excluded_headers' => ['additional_excluded_headers', ['X-Custom']],
            'allowed_headers' => ['allowed_headers', ['Content-Type']],
        ];
    }

    public function testBuildLogsASingleWarningNamingEveryDeprecatedAttributePresent()
    {
        $logger = $this->captureLogs();

        CacheStalenessMiddleware::build($this->attributes([
            'allowed_headers' => ['Content-Type'],
            'excluded_headers' => [],
            'mode' => 'deny',
        ]));

        $this->assertSame(
            [['warn', $this->deprecationMessage('mode, excluded_headers, allowed_headers')]],
            $logger->lines
        );
    }

    public function testBuildWarnsWhenADeprecatedAttributeIsPresentWithNullValue()
    {
        $logger = $this->captureLogs();

        CacheStalenessMiddleware::build($this->attributes(['excluded_headers' => null]));

        $this->assertSame([['warn', $this->deprecationMessage('excluded_headers')]], $logger->lines);
    }

    public function testBuildLogsNothingWithoutDeprecatedAttributes()
    {
        $logger = $this->captureLogs();

        CacheStalenessMiddleware::build($this->attributes(['evictOn' => ['4xx']]));

        $this->assertSame([], $logger->lines);
    }

    public function testBuildDoesNotValidateDeprecatedMode()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes(['mode' => 'invalid']));

        $this->assertInstanceOf(CacheStalenessMiddleware::class, $middleware);
    }

    public function testBuildDoesNotRequireAllowedHeadersInDeprecatedAllowMode()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes(['mode' => 'allow']));

        $this->assertInstanceOf(CacheStalenessMiddleware::class, $middleware);
    }

    private function captureLogs(): CapturingLoggerInstance
    {
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        return $logger;
    }

    private function deprecationMessage(string $names): string
    {
        return '[deprecated] - CacheStalenessMiddleware ignores option(s): ' . $names .
            ' — the cache refresh uses the header filter of FileCacheMiddleware; ' .
            'they will be removed in the next major version';
    }

    public function testBuildDefaultsToEmptyEvictionPolicy()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes());

        $this->assertTrue($this->evictionPolicy($middleware)->isEmpty());
    }

    public function testBuildParsesEvictOn()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes(['evictOn' => ['4xx']]));

        $this->assertTrue($this->evictionPolicy($middleware)->evicts(404));
        $this->assertFalse($this->evictionPolicy($middleware)->evicts(500));
    }

    public function testBuildParsesSnakeCaseEvictOn()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes(['evict_on' => [500]]));

        $this->assertTrue($this->evictionPolicy($middleware)->evicts(500));
    }

    public function testBuildCamelCaseEvictOnWins()
    {
        $middleware = CacheStalenessMiddleware::build(
            $this->attributes(['evictOn' => [404], 'evict_on' => [500]])
        );

        $this->assertTrue($this->evictionPolicy($middleware)->evicts(404));
        $this->assertFalse($this->evictionPolicy($middleware)->evicts(500));
    }

    public function testBuildExplicitEmptyEvictOnNeverEvicts()
    {
        $middleware = CacheStalenessMiddleware::build($this->attributes(['evictOn' => []]));

        $this->assertTrue($this->evictionPolicy($middleware)->isEmpty());
    }

    public function testBuildThrowsWhenEvictOnIsNotAnArray()
    {
        $this->expectException(\InvalidArgumentException::class);

        CacheStalenessMiddleware::build($this->attributes(['evictOn' => '4xx']));
    }

    public function testBuildThrowsWhenEvictOnHasInvalidEntry()
    {
        $this->expectException(\InvalidArgumentException::class);

        CacheStalenessMiddleware::build($this->attributes(['evictOn' => ['abc']]));
    }

    private function attributes(array $extra = []): array
    {
        return array_merge([
            'location' => '/tmp/cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300,
        ], $extra);
    }

    private function evictionPolicy(CacheStalenessMiddleware $middleware)
    {
        $property = (new \ReflectionClass($middleware))->getProperty('evictionPolicy');
        $property->setAccessible(true);
        return $property->getValue($middleware);
    }
}
