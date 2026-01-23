<?php

namespace Tent\Models;

use Tent\Models\Response;

interface Cache extends ResponseContent
{
    public function store(Response $response): void;
}
