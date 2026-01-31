<?php

namespace Tent\Models\ResponseMatchers;

interface ResponseMatcher
{
    public function match(Response $response): bool;
}