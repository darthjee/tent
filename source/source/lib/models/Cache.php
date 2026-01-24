<?php

namespace Tent\Models;

use Tent\Models\Response;

interface Cache extends ResponseContent
{
    /**
     * Stores the response in the cache.
     *
     * @param Response $response The response to store.
     * @return void
     */
    public function store(Response $response): void;
}
