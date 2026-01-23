<?php

namespace Tent\Service;

use Tent\Models\Response;
use Tent\Models\FileCache;

class CacheWritter
{
    private Response $response;
    private FileCache $cache;

    public function __construct(Response $response, FileCache $cache)
    {
        $this->response = $response;
        $this->cache = $cache;
    }
}
