<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;

class HttpCodeMatcher implements ResponseMatcher
{
    private array $httpCodes;

    public function __construct(array $httpCodes) {
        $this->httpCodes = $httpCodes;
    }

    public function match(Response $response): bool
    {
        return HttpCodeMatcher::matchAny($response->getHttpCode(), $this->httpCodes);
    }
}