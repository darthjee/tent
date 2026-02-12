<?php

namespace Tent\Matchers;

use Tent\Models\Response;

/**
 * Matcher for HTTP request methods associated with responses.
 */
class RequestMethodMatcher extends ResponseMatcher
{
    /**
     * @var array The list of HTTP request methods to match against.
     */
    private array $requestMethods;

    /**
     * Constructs a RequestMethodMatcher with the given list of HTTP request methods.
     *
     * @param array $requestMethods The list of HTTP request methods to match against.
     */
    public function __construct(array $requestMethods)
    {
        $this->requestMethods = array_map('strtoupper', $requestMethods);
    }

    /**
     * Checks if the response's associated request method matches any of the configured methods.
     *
     * @param Response $response The response to check.
     * @return boolean True if the request method matches, false otherwise.
     */
    public function match(Response $response): bool
    {
        $request = $response->request();
        $method = strtoupper($request->requestMethod());

        return in_array($method, $this->requestMethods);
    }

    /**
     * Builds a RequestMethodMatcher from the given attributes.
     *
     * @param array $attributes The attributes for building the matcher.
     * @return RequestMethodMatcher The constructed RequestMethodMatcher.
     */
    public static function build(array $attributes): RequestMethodMatcher
    {
        $requestMethods = $attributes['requestMethods'] ?? ['GET'];
        return new self($requestMethods);
    }
}
