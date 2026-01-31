<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;

class StatusCodeMatcher implements ResponseMatcher
{
    private array $httpCodes;

    public function __construct(array $httpCodes) {
        $this->httpCodes = $httpCodes;
    }

    public function match(Response $response): bool
    {
        return \Tent\Utils\HttpCodeMatcher::matchAny($response->httpCode(), $this->httpCodes);
    }
}