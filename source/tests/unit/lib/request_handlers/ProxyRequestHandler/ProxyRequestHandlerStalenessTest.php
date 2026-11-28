<?php

namespace Tent\Tests\RequestHandlers\ProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\Middleware;
use Tent\Tests\Support\Cache\DummyRequestHasher;
use Tent\Tests\Support\Handlers\StaleCacheScenario;
use Tent\Tests\Support\Middlewares\QuickResponseMiddleware;

class ProxyRequestHandlerStalenessTest extends TestCase
{
    use StaleCacheScenario;

    public function testStaleHitSchedulesARefresh()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy([$this->fileCacheConfig(), $this->stalenessConfig()]);

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry()->content());
    }

    public function testNextRequestIsServedTheRefreshedEntry()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy([$this->fileCacheConfig(), $this->stalenessConfig()]);

        $handler->handleRequest($this->buildRequest());
        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('fresh body', $response->body());
        $this->assertSame(1, $this->scheduled);
    }

    public function testFreshHitSchedulesNoRefresh()
    {
        $this->storeEntry(10);
        $handler = $this->buildProxy([$this->fileCacheConfig(), $this->stalenessConfig()]);

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(0, $this->scheduled);
    }

    public function testStalenessPlacedBeforeTheCacheSchedulesARefresh()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy([$this->stalenessConfig(), $this->fileCacheConfig()]);

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry()->content());
    }

    public function testPrependedStalenessSchedulesARefresh()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy([$this->fileCacheConfig()], [$this->stalenessConfig()]);

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry()->content());
    }

    public function testRequestMiddlewaresAfterTheCacheAreStillSkippedOnAHit()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy([$this->fileCacheConfig(), $this->stalenessConfig()]);

        $spy = $this->createMock(Middleware::class);
        $spy->expects($this->never())->method('processRequest');
        $spy->expects($this->once())->method('processResponse')->willReturnArgument(0);
        $handler->addMiddleware($spy);

        $handler->handleRequest($this->buildRequest());

        $this->assertSame(1, $this->scheduled);
    }

    public function testResponseSetByAnotherMiddlewareSchedulesNoRefresh()
    {
        $this->storeEntry(1000);
        $handler = $this->buildProxy(
            [$this->fileCacheConfig(), $this->stalenessConfig()],
            [['class' => QuickResponseMiddleware::class]]
        );

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('Quick Response', $response->body());
        $this->assertSame(0, $this->scheduled);
        $this->assertSame('cached body', $this->entry()->content());
    }

    public function testEntryEvictedOnReadSchedulesNoRefresh()
    {
        $this->storeEntry(1000, 404);
        $handler = $this->buildProxy(
            [$this->fileCacheConfig(['evictOn' => ['4xx']]), $this->stalenessConfig()],
            [],
            ['body' => 'upstream body', 'httpCode' => 200, 'headers' => []]
        );

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('upstream body', $response->body());
        $this->assertSame(0, $this->scheduled);
    }

    public function testCustomRequestHasherRefreshesTheServedEntry()
    {
        $this->storeEntry(1000, 200, new DummyRequestHasher());
        $handler = $this->buildProxy([
            $this->fileCacheConfig(['request_hasher' => ['class' => DummyRequestHasher::class]]),
            $this->stalenessConfig(),
        ]);

        $response = $handler->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry(new DummyRequestHasher())->content());
        $this->assertFalse($this->entry()->exists());
    }

    public function testRefreshAppliesTheHeaderFilterOfTheFileCache()
    {
        $this->refreshResult['headers'] = ['X-Test: 1', 'X-Secret: 1', 'Set-Cookie: session=abc'];
        $this->storeEntry(1000);
        $handler = $this->buildProxy([
            $this->fileCacheConfig(['additional_excluded_headers' => ['X-Secret']]),
            $this->stalenessConfig(['excluded_headers' => []]),
        ]);

        $handler->handleRequest($this->buildRequest());

        $headers = $this->entry()->headers();
        $this->assertContains('X-Test: 1', $headers);
        $this->assertNotContains('X-Secret: 1', $headers);
        $this->assertNotContains('Set-Cookie: session=abc', $headers);
    }

    private function buildProxy(array $middlewares, array $prepended = [], ?array $upstreamResult = null)
    {
        return $this->buildHandler([
            'type' => 'proxy',
            'host' => 'http://backend:8080',
            'prependMiddlewares' => $prepended,
            'middlewares' => $middlewares,
        ], $upstreamResult);
    }
}
