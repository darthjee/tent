<?php

namespace Tent\Matchers;

use Tent\Models\Response;
use Tent\Utils\CurlUtils;

/**
 * Matcher for HTTP response headers.
 *
 * Checks if a response contains any of the specified headers with matching values.
 * Useful for conditional caching based on response headers.
 */
class ResponseHeaderMatcher extends RequestResponseMatcher
{
    /**
     * @var array Associative array of header name => expected value to match against.
     */
    private array $headers;

    /**
     * Constructs a ResponseHeaderMatcher with the given header name => value pairs.
     *
     * @param array $headers Associative array of header name => expected value.
     */
    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Checks if the response contains any of the configured headers with matching values.
     *
     * Header name matching is case-insensitive; value matching is case-sensitive.
     * Whitespace is trimmed from header values when comparing.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response contains any matching header and value, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        $responseHeaders = CurlUtils::mapHeaderLines($response->headers());

        foreach ($this->headers as $name => $expectedValue) {
            $lowerName = strtolower($name);
            if (isset($responseHeaders[$lowerName]) && $responseHeaders[$lowerName] === $expectedValue) {
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
        $headers = $attributes['headers'] ?? [];
        return new self($headers);
    }
}
