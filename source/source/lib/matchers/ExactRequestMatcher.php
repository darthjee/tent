<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;

/**
 * Matches an incoming Request using exact URI equality.
 *
 * The request URI must be exactly equal to the configured $requestUri.
 */
class ExactRequestMatcher extends RequestMatcher
{
    /**
     * Builds an ExactRequestMatcher from an associative array.
     *
     * Example:
     *   ExactRequestMatcher::build(['method' => 'GET', 'uri' => '/users'])
     *
     * @param array $params Associative array with keys 'method' and 'uri'.
     * @return ExactRequestMatcher
     */
    public static function build(array $params): self
    {
        return new self($params['method'] ?? null, $params['uri'] ?? null);
    }

    /**
     * Checks if the request URI exactly matches the configured URI.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request URI exactly equals the configured URI (or URI is null).
     */
    protected function matchRequestUri(RequestInterface $request)
    {
        if ($this->requestUri == null) {
            return true;
        }

        return $request->requestPath() === $this->requestUri;
    }
}
