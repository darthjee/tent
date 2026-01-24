<?php

namespace Tent\Service;

use Tent\Models\Response;
use Tent\Models\FileCache;

class CacheWriter
{
    private Response $response;
    private FileCache $cache;

    /**
     * Constructs a CacheWriter object.
     *
     * @param Response  $response The response to be cached.
     * @param FileCache $cache    The file cache instance.
     */
    public function __construct(Response $response, FileCache $cache)
    {
        $this->response = $response;
        $this->cache = $cache;
    }

    /**
     * Writes the response to the cache.
     *
     * @return void
     */
    public function write(): void
    {
        $this->cache->store($this->response);
    }
}