<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareCacheHitTest extends TestCase
{
    private string $cacheDir;
    private FolderLocation $location;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_hit_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testSetsCacheHitOnCacheHit()
    {
        $request = $this->buildRequest();
        $this->storeCache($request);

        $this->buildMiddleware()->processRequest($request);

        $this->assertNotNull($request->cacheHit());
    }

    public function testCacheHitHoldsTheServedEntry()
    {
        $request = $this->buildRequest();
        $this->storeCache($request);

        $this->buildMiddleware()->processRequest($request);

        $this->assertEquals(
            (new FileCache($request, $this->location))->metaFilePath(),
            $request->cacheHit()->cache()->metaFilePath()
        );
        $this->assertEquals('cached body', $request->cacheHit()->cache()->content());
    }

    public function testCacheHitHoldsTheMiddlewareHeaderFilter()
    {
        $request = $this->buildRequest();
        $this->storeCache($request);

        $this->buildMiddleware(['additional_excluded_headers' => ['X-Secret']])
            ->processRequest($request);

        $filtered = $request->cacheHit()->headerFilter()->filter(new Response([
            'headers' => ['Content-Type: text/plain', 'X-Secret: 1', 'Set-Cookie: a=1'],
            'request' => $request,
        ]));

        $this->assertEquals(['Content-Type: text/plain'], array_values($filtered->headers()));
    }

    public function testDoesNotSetCacheHitOnCacheMiss()
    {
        $request = $this->buildRequest();

        $this->buildMiddleware()->processRequest($request);

        $this->assertNull($request->cacheHit());
    }

    public function testDoesNotSetCacheHitWhenEntryIsEvictedOnRead()
    {
        $request = $this->buildRequest();
        $this->storeCache($request, 404);

        $this->buildMiddleware(['evictOn' => ['4xx']])->processRequest($request);

        $this->assertNull($request->cacheHit());
    }

    public function testDoesNotSetCacheHitWhenAMatcherDoesNotMatch()
    {
        $request = $this->buildRequest();
        $this->storeCache($request);

        $this->buildMiddleware([
            'matchers' => [
                ['class' => \Tent\Matchers\RequestMethodMatcher::class, 'requestMethods' => ['POST']]
            ]
        ])->processRequest($request);

        $this->assertNull($request->cacheHit());
    }

    public function testDoesNotSetCacheHitWhenCacheIsSkipped()
    {
        $request = $this->buildRequest();
        $this->storeCache($request);
        $request->skipCache();

        $this->buildMiddleware()->processRequest($request);

        $this->assertNull($request->cacheHit());
    }

    public function testDoesNotSetCacheHitWhenSkipCacheHeaderIsPresent()
    {
        $request = $this->buildRequest(['X-Skip-Cache' => '1']);
        $this->storeCache($request);

        $this->buildMiddleware(['skip_cache_header' => 'X-Skip-Cache'])->processRequest($request);

        $this->assertNull($request->cacheHit());
    }

    private function buildRequest(array $headers = []): ProcessingRequest
    {
        return new ProcessingRequest([
            'headers' => $headers,
            'requestPath' => '/file.txt',
            'requestMethod' => 'GET'
        ]);
    }

    private function buildMiddleware(array $attributes = []): FileCacheMiddleware
    {
        $attributes['location'] = $this->cacheDir;
        return FileCacheMiddleware::build($attributes);
    }

    private function storeCache(ProcessingRequest $request, int $httpCode = 200): void
    {
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => $httpCode,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]);
        (new FileCache($request, $this->location))->store($response);
    }
}
