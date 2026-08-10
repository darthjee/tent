<?php

namespace Tent\Service;

use Tent\Content\Cache;
use Tent\Content\HeaderFilter;
use Tent\Models\Response;

/**
 * Service for caching responses.
 *
 * Optionally applies a `HeaderFilter` to the response before storing it, so that
 * dangerous headers (e.g. `Set-Cookie`) never get persisted into the cache and
 * later replayed to a different client on a future cache hit. Both existing
 * cache-write call sites — `Tent\Middlewares\FileCacheMiddleware::processResponse()`
 * and `Tent\Service\BackgroundRefresher::replaceCache()` — funnel through this class,
 * so any future write call site automatically inherits the same protection.
 */
class ResponseCacher
{
    /**
     * @var Cache The cache instance to store the response.
     */
    private Cache $cache;

    /**
     * @var Response The response to be cached.
     */
    private Response $response;

    /**
     * @var HeaderFilter|null Optional filter applied to the response's headers before storing.
     */
    private ?HeaderFilter $headerFilter;

    /**
     * Constructs a ResponseCacher instance.
     *
     * @param Cache             $cache        The cache instance to store the response.
     * @param Response          $response     The response to be cached.
     * @param HeaderFilter|null $headerFilter Optional filter applied to the response's headers
     *                                        before storing. When null, headers are stored as-is.
     */
    public function __construct(Cache $cache, Response $response, ?HeaderFilter $headerFilter = null)
    {
        $this->cache = $cache;
        $this->response = $response;
        $this->headerFilter = $headerFilter;
    }

    /**
     * Processes the response and stores it in the cache if it does not already exist.
     *
     * When a `HeaderFilter` is configured, it is applied to the response before storing,
     * so only the filtered headers ever reach the cache.
     *
     * @return void
     */
    public function process(): void
    {
        if (!$this->cache->exists()) {
            $response = $this->headerFilter?->filter($this->response) ?? $this->response;
            $this->cache->store($response);
        }
    }
}
