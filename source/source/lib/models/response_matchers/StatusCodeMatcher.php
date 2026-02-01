<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;
use Tent\Utils\HttpCodeMatcher;

/**
 * Matcher for HTTP status codes in responses.
 */
class StatusCodeMatcher implements ResponseMatcher
{
    /**
     * @var array The list of HTTP status codes or patterns to match against.
     */
    private array $httpCodes;

    /**
     * Constructs a StatusCodeMatcher with the given list of HTTP status codes or patterns.
     *
     * @param array $httpCodes The list of HTTP status codes or patterns to match against.
     */
    public function __construct(array $httpCodes) {
        $this->httpCodes = $httpCodes;
    }

    /**
     * Checks if the response's HTTP status code matches any of the configured codes.
     *
     * @param Response $response The response to check.
     * @return bool True if the response's status code matches, false otherwise.
     */
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