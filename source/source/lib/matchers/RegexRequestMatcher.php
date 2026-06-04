<?php

namespace Tent\Matchers;

use InvalidArgumentException;
use Tent\Models\RequestInterface;

class RegexRequestMatcher extends RequestMatcher
{
    /**
     * Builds a RegexRequestMatcher from matcher attributes.
     *
     * @param array $params Associative matcher attributes.
     * @return RegexRequestMatcher
     */
    public static function build(array $params): self
    {
        if (!array_key_exists('pattern', $params)) {
            throw new InvalidArgumentException('Missing required regex pattern.');
        }

        $pattern = $params['pattern'];

        if (!is_string($pattern) || $pattern === '') {
            throw new InvalidArgumentException('Regex pattern must be a non-empty string.');
        }

        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(sprintf("Invalid regex pattern '%s'.", $pattern));
        }

        return new self($params['method'] ?? null, $pattern);
    }

    /**
     * Checks whether the request path matches the configured regex.
     *
     * @param RequestInterface $request Incoming request.
     * @return boolean
     */
    protected function matchRequestUri(RequestInterface $request): bool
    {
        return preg_match($this->requestUri, $request->requestPath()) === 1;
    }
}
