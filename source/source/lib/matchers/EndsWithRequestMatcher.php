<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;

/**
 * Matches an incoming Request using suffix-based URI matching.
 *
 * The request URI must end with the configured $requestUri.
 */
class EndsWithRequestMatcher extends RequestMatcher
{
    /**
     * Builds an EndsWithRequestMatcher from an associative array.
     *
     * Example:
     *   EndsWithRequestMatcher::build(['method' => 'GET', 'uri' => '.json'])
     *
     * @param array $params Associative array with keys 'method' and 'uri'.
     * @return EndsWithRequestMatcher
     */
    public static function build(array $params): self
    {
        return new self($params['method'] ?? null, $params['uri'] ?? null);
    }

    /**
     * Checks if the request URI ends with the configured URI.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request URI ends with the configured URI (or URI is null).
     */
    protected function matchRequestUri(RequestInterface $request): bool
    {
        if ($this->requestUri === null) {
            return true;
        }

        return str_ends_with($request->requestPath(), $this->requestUri);
    }
}
