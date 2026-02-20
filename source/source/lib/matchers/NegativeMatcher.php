<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;
use Tent\Models\Response;

/**
 * A negative/inverse wrapper matcher that inverts the result of another RequestResponseMatcher.
 *
 * Returns true when the wrapped matcher returns false, and vice versa.
 */
class NegativeMatcher extends RequestResponseMatcher
{
    /**
     * @var RequestResponseMatcher The wrapped matcher whose result will be inverted.
     */
    private RequestResponseMatcher $matcher;

    /**
     * Constructs a NegativeMatcher wrapping the given matcher.
     *
     * @param RequestResponseMatcher $matcher The matcher whose result will be inverted.
     */
    public function __construct(RequestResponseMatcher $matcher)
    {
        $this->matcher = $matcher;
    }

    /**
     * Returns the inverse of the wrapped matcher's matchRequest result.
     *
     * @param RequestInterface $request The request to check.
     * @return boolean True if the wrapped matcher returns false, false otherwise.
     */
    public function matchRequest(RequestInterface $request): bool
    {
        return !$this->matcher->matchRequest($request);
    }

    /**
     * Returns the inverse of the wrapped matcher's matchResponse result.
     *
     * @param Response $response The response to check.
     * @return boolean True if the wrapped matcher returns false, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        return !$this->matcher->matchResponse($response);
    }

    /**
     * Builds a NegativeMatcher from the given attributes.
     *
     * The attributes must contain a 'matcher' key with parameters to build the wrapped matcher.
     *
     * @param array $attributes The attributes for building the matcher.
     * @return NegativeMatcher The constructed NegativeMatcher.
     */
    public static function build(array $attributes): NegativeMatcher
    {
        $matcherParams = $attributes['matcher'] ?? [];
        $wrappedMatcher = RequestResponseMatcher::build($matcherParams);
        return new self($wrappedMatcher);
    }
}
