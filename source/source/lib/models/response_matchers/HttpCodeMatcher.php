<?php

namespace Tent\Models\ResponseMatchers;

class HttpCodeMatcher implements ResponseMatcher
{
    private array $httpCodes;

    public function __construct(array $httpCodes) {
        $this->httpCodes = $httpCodes;
    }

    public function match(Response $response): bool
    {
        return in_array($response->getHttpCode(), $this->httpCodes, true);
    }
}