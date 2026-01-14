<?php

namespace Tent;

/**
 * Matches an incoming Request against method and URI criteria.
 *
 * RequestMatcher is used by Rule to determine if a given Request should be handled by a specific RequestHandler.
 * A Rule can have multiple RequestMatchers and one RequestHandler. Matching can be exact or prefix-based.
 */
class RequestMatcher
{
    private $requestMethod;
    private $requestUri;
    private $matchType;

    /**
     * @param string|null $requestMethod HTTP method to match (e.g., GET, POST), or null for any.
     * @param string|null $requestUri URI to match, or null for any.
     * @param string $matchType Type of URI match: 'exact' or 'begins_with'.
     */
    public function __construct($requestMethod = null, $requestUri = null, $matchType = 'exact')
    {
        $this->requestMethod = $requestMethod;
        $this->requestUri = $requestUri;
        $this->matchType = $matchType;
    }

    /**
     * Checks if the given Request matches this matcher.
     *
     * @param Request $request The incoming HTTP request.
     * @return bool True if the request matches method and URI criteria.
     */
    public function matches($request)
    {
        return $this->matchRequestMethod($request) && $this->matchRequestUri($request);
    }

    /**
     * Checks if the request method matches.
     *
     * @param Request $request
     * @return bool
     */
    private function matchRequestMethod($request)
    {
        return $this->requestMethod == null || $request->requestMethod() == $this->requestMethod;
    }

    /**
     * Checks if the request URI matches according to matchType.
     *
     * @param Request $request
     * @return bool
     */
    private function matchRequestUri($request)
    {
        if ($this->requestUri == null) {
            return true;
        }

        $requestUrl = $request->requestUrl();

        if ($this->matchType === 'exact') {
            return $requestUrl === $this->requestUri;
        } elseif ($this->matchType === 'begins_with') {
            return strpos($requestUrl, $this->requestUri) === 0;
        }

        return false;
    }
}
