<?php

namespace Tent\Service;

use Tent\Models\Cache;
use Tent\Models\Response;

class ResponseCacher
{
    private Cache $cache;
    private Response $response;

    public function __construct(Cache $cache, Response $response)
    {
        $this->cache = $cache;
        $this->response = $response;
    }

    public function process(): void
    {
        $this->cache->store($this->response);
    }
}
