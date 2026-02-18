<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;
use Tent\Models\Response;

/**
 * Matcher for HTTP request methods associated with responses.
 */
class RequestMethodMatcher extends RequestResponseMatcher
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
     * Checks if the request's method matches any of the configured methods.
     *
     * @param RequestInterface $request The request to check.
     * @return boolean True if the request method matches, false otherwise.
     */
    public function matchRequest(RequestInterface $request): bool
    {
        return $this->match($request);
    }

    public function matchResponse(Response $response): bool
    {
        return $this->match($response->request());
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

    private function match(RequestInterface $request): bool
    {
        $method = strtoupper($request->requestMethod());

        return in_array($method, $this->requestMethods);
    }
}
