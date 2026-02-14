<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Utils\HttpCodeMatcher;

/**
 * Matcher for HTTP status codes in responses.
 */
class StatusCodeMatcher extends RequestResponseMatcher
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
    public function __construct(array $httpCodes)
    {
        $this->httpCodes = $httpCodes;
    }

    /**
     * Checks if the response's HTTP status code matches any of the configured codes.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response's status code matches, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        $target = $response->httpCode();

        foreach ($this->httpCodes as $code) {
            if (new HttpCodeMatcher($code)->match($target)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Request matching is not constrained for status code matchers.
     *
     * @param RequestInterface $request The request to check.
     * @return boolean Always true.
     */
    public function matchRequest(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * Builds a StatusCodeMatcher from the given attributes.
     *
     * @param array $attributes The attributes for building the matcher.
     * @return StatusCodeMatcher The constructed StatusCodeMatcher.
     */
    public static function build(array $attributes): StatusCodeMatcher
    {
        $httpCodes = $attributes['httpCodes'] ?? [200];
        return new self($httpCodes);
    }
}
