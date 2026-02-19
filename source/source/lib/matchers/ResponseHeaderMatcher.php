<?php

namespace Tent\Matchers;

use Tent\Models\Response;

/**
 * Matcher for HTTP response headers.
 *
 * Checks if a response contains any of the specified headers.
 * Useful for conditional caching based on response headers.
 */
class ResponseHeaderMatcher extends RequestResponseMatcher
{
    /**
     * @var array The list of header names to match against (lowercase).
     */
    private array $headerNames;

    /**
     * Constructs a ResponseHeaderMatcher with the given list of header names.
     *
     * @param array $headerNames The list of header names to match against.
     */
    public function __construct(array $headerNames)
    {
        $this->headerNames = array_map('strtolower', $headerNames);
    }

    /**
     * Checks if the response contains any of the configured header names.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response contains any matching header, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        $responseHeaderNames = array_map(function ($header) {
            return strtolower(explode(':', $header, 2)[0]);
        }, $response->headers());

        foreach ($this->headerNames as $name) {
            if (in_array($name, $responseHeaderNames)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds a ResponseHeaderMatcher from the given attributes.
     *
     * @param array $attributes The attributes for building the matcher.
     * @return ResponseHeaderMatcher The constructed ResponseHeaderMatcher.
     */
    public static function build(array $attributes): ResponseHeaderMatcher
    {
        $headerNames = $attributes['headerNames'] ?? [];
        return new self($headerNames);
    }
}
