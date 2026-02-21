<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;

/**
 * Matches an incoming Request using prefix-based URI matching.
 *
 * The request URI must start with the configured $requestUri.
 */
class BeginsWithRequestMatcher extends RequestMatcher
{
    /**
     * Builds a BeginsWithRequestMatcher from an associative array.
     *
     * Example:
     *   BeginsWithRequestMatcher::build(['method' => 'GET', 'uri' => '/assets/'])
     *
     * @param array $params Associative array with keys 'method' and 'uri'.
     * @return BeginsWithRequestMatcher
     */
    public static function build(array $params): self
    {
        return new self($params['method'] ?? null, $params['uri'] ?? null);
    }

    /**
     * Checks if the request URI starts with the configured URI.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request URI starts with the configured URI (or URI is null).
     */
    protected function matchRequestUri(RequestInterface $request)
    {
        if ($this->requestUri == null) {
            return true;
        }

        return strpos($request->requestPath(), $this->requestUri) === 0;
    }
}
