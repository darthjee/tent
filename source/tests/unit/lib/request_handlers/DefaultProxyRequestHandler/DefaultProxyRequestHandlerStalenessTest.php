<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Tests\Support\Handlers\StaleCacheScenario;

class DefaultProxyRequestHandlerStalenessTest extends TestCase
{
    use StaleCacheScenario;

    public function testStaleHitSchedulesARefresh()
    {
        $this->storeEntry(1000);

        $response = $this->buildDefaultProxy('middlewares')->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry()->content());
    }

    public function testFreshHitSchedulesNoRefresh()
    {
        $this->storeEntry(10);

        $response = $this->buildDefaultProxy('middlewares')->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(0, $this->scheduled);
    }

    public function testPrependedStalenessSchedulesARefresh()
    {
        $this->storeEntry(1000);

        $response = $this->buildDefaultProxy('prependMiddlewares')->handleRequest($this->buildRequest());

        $this->assertSame('cached body', $response->body());
        $this->assertSame(1, $this->scheduled);
        $this->assertSame('fresh body', $this->entry()->content());
    }

    public function testRefreshAppliesTheHeaderFilterOfTheHandlerCache()
    {
        $this->refreshResult['headers'] = ['X-Test: 1', 'X-Secret: 1', 'Set-Cookie: session=abc'];
        $this->storeEntry(1000);

        $this->buildDefaultProxy('middlewares', ['additional_excluded_headers' => ['X-Secret']])
            ->handleRequest($this->buildRequest());

        $headers = $this->entry()->headers();
        $this->assertContains('X-Test: 1', $headers);
        $this->assertNotContains('X-Secret: 1', $headers);
        $this->assertNotContains('Set-Cookie: session=abc', $headers);
    }

    private function buildDefaultProxy(string $placement, array $params = [])
    {
        return $this->buildHandler(array_merge([
            'type' => 'default_proxy',
            'host' => 'http://backend:8080',
            'cache' => $this->cacheDir,
            $placement => [$this->stalenessConfig()],
        ], $params));
    }
}
