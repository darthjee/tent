<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;
use Tent\Utils\HttpCodeMatcher;

class StatusCodeMatcher implements ResponseMatcher
{
    private array $httpCodes;

    public function __construct(array $httpCodes) {
        $this->httpCodes = $httpCodes;
    }

    public function match(Response $response): bool
    {
        $target = $response->httpCode();

        foreach ($this->httpCodes as $code) {
            if (new HttpCodeMatcher($code)->match($target)) {
                return true;
            }
        }
        return false;
    }
}