<?php

namespace Tent\Service;

use Tent\Content\Cache;
use Tent\Models\Response;

/**
 * Service for caching responses.
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
     * Constructs a ResponseCacher instance.
     *
     * @param Cache    $cache    The cache instance to store the response.
     * @param Response $response The response to be cached.
     */
    public function __construct(Cache $cache, Response $response)
    {
        $this->cache = $cache;
        $this->response = $response;
    }

    /**
     * Processes the response and stores it in the cache if it does not already exist.
     *
     * @return void
     */
    public function process(): void
    {
        if (!$this->cache->exists()) {
            $this->cache->store($this->response);
        }
    }
}
