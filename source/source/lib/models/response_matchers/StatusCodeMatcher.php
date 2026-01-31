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
        return HttpCodeMatcher::matchAny($response->httpCode(), $this->httpCodes);
    }
}