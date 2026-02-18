<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Utils\Logger;

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
        $this->assertCount(2, $matchers);
        $matcher = $matchers[0];
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertTrue($matcher->matchResponse($response));
        $response = new \Tent\Models\Response(['httpCode' => 201]);
        $this->assertFalse($matcher->matchResponse($response));
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
        $this->assertTrue($matcher->matchResponse($response));
        $response = new \Tent\Models\Response(['httpCode' => 200]);
        $this->assertFalse($matcher->matchResponse($response));
    }

    public function testBuildWithRequestMethodsTriggersDeprecationWarning()
    {
        // Create a custom logger to capture deprecation warnings
        $warnings = [];
        $testLogger = new class ($warnings) implements \Tent\Utils\LoggerInterface {
            private $warnings;
            public function __construct(&$warnings)
            {
                $this->warnings = &$warnings;
            }
            public function logDeprecation(string $message): void
            {
                $this->warnings[] = $message;
            }
        };

        // Set the custom logger
        $originalLogger = Logger::getInstance();
        Logger::setInstance($testLogger);

        FileCacheMiddleware::build([
            'location' => '/tmp/cache',
            'requestMethods' => ['GET', 'POST']
        ]);

        // Verify deprecation warning was logged
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('requestMethods', $warnings[0]);
        $this->assertStringContainsString('deprecated', $warnings[0]);

        // Restore original logger
        Logger::setInstance($originalLogger);
    }
}
