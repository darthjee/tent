<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;

interface ResponseMatcher
{
    public function match(Response $response): bool;
}
