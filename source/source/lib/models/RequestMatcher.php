<?php

namespace Tent\Models;

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
     * @param string|null $requestUri    URI to match, or null for any.
     * @param string      $matchType     URI match type:
     *            - 'exact': the request URI must be exactly equal to $requestUri.
     *            - 'begins_with': the request URI must start with $requestUri (prefix match).
     */
    public function __construct(?string $requestMethod = null, ?string $requestUri = null, string $matchType = 'exact')
    {
        $this->requestMethod = $requestMethod;
        $this->requestUri = $requestUri;
        $this->matchType = $matchType;
    }

    /**
     * Builds a RequestMatcher from an associative array.
     *
     * Example:
     *   RequestMatcher::build(['method' => 'GET', 'uri' => '/users', 'type' => 'exact'])
     *
     * @param array $params Associative array with keys 'method', 'uri', 'type'.
     * @return RequestMatcher
     */
    public static function build(array $params): self
    {
        return new self(
            $params['method'] ?? null,
            $params['uri'] ?? null,
            $params['type'] ?? 'exact'
        );
    }

    /**
     * Checks if the given Request matches this matcher.
     *
     * @param Request $request The incoming HTTP request.
     * @return boolean True if the request matches method and URI criteria.
     */
    public function matches(Request $request)
    {
        return $this->matchRequestMethod($request) && $this->matchRequestUri($request);
    }

    /**
     * Checks if the request method matches.
     *
     * @param Request $request The incoming HTTP request.
     * @return boolean True if the request matches http method criteria.
     */
    private function matchRequestMethod(Request $request)
    {
        return $this->requestMethod == null || $request->requestMethod() == $this->requestMethod;
    }

    /**
     * Checks if the request URI matches according to matchType.
     *
     * Available matchType values:
     *   - 'exact': requires the request URI to be exactly equal to $requestUri.
     *   - 'begins_with': requires the request URI to start with $requestUri (prefix match).
     *
     * @param Request $request The incoming HTTP request.
     * @return boolean True if the request matches URI criteria.
     */
    private function matchRequestUri(Request $request)
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
