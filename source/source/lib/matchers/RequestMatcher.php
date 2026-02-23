<?php

namespace Tent\Matchers;

use InvalidArgumentException;
use Tent\Models\RequestInterface;

/**
 * Abstract base class for matching an incoming Request against method and URI criteria.
 *
 * RequestMatcher is used by Rule to determine if a given Request should be handled by a specific RequestHandler.
 * A Rule can have multiple RequestMatchers and one RequestHandler.
 *
 * Subclasses implement the URI matching strategy (exact, begins_with, ends_with, etc.).
 */
abstract class RequestMatcher
{
    protected $requestMethod;
    protected $requestUri;

    /**
     * @param string|null $requestMethod HTTP method to match (e.g., GET, POST), or null for any.
     * @param string|null $requestUri    URI to match, or null for any.
     */
    public function __construct(?string $requestMethod = null, ?string $requestUri = null)
    {
        $this->requestMethod = $requestMethod;
        $this->requestUri = $requestUri;
    }

    /**
     * Builds a RequestMatcher from an associative array.
     *
     * Instantiates the appropriate subclass based on the 'type' parameter.
     * Defaults to 'exact' if 'type' is not provided.
     *
     * Example:
     *   RequestMatcher::build(['method' => 'GET', 'uri' => '/users', 'type' => 'exact'])
     *   RequestMatcher::build(['method' => 'GET', 'uri' => '/assets/', 'type' => 'begins_with'])
     *   RequestMatcher::build(['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with'])
     *
     * @param array $params Associative array with keys 'method', 'uri', 'type'.
     * @return RequestMatcher
     * @throws InvalidArgumentException when 'type' does not map to a valid RequestMatcher class.
     */
    public static function build(array $params): self
    {
        $type = $params['type'] ?? 'exact';
        $matcherClass = __NAMESPACE__ . '\\' . self::toStudlyCase($type) . 'RequestMatcher';

        if (class_exists($matcherClass) && is_subclass_of($matcherClass, self::class)) {
            return $matcherClass::build($params);
        }

        throw new InvalidArgumentException(sprintf("Unknown matcher type '%s'.", $type));
    }

    private static function toStudlyCase(string $value): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', $value, -1, PREG_SPLIT_NO_EMPTY);
        return implode('', array_map('ucfirst', array_map('strtolower', $parts)));
    }

    /**
     * Builds several RequestMatchers.
     *
     * @param array $attributes Array of associative arrays, each with keys 'method', 'uri', 'type'.
     * @see RequestMatcher::build
     * @return array all RequestMatchers.
     */
    public static function buildMatchers(array $attributes): array
    {
        $matchers = [];
        foreach ($attributes as $attributes) {
            $matchers[] = self::build($attributes);
        }
        return $matchers;
    }

    /**
     * Checks if the given Request matches this matcher.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request matches method and URI criteria.
     */
    public function matches(RequestInterface $request)
    {
        return $this->matchRequestMethod($request) && $this->matchRequestUri($request);
    }

    /**
     * Checks if the request method matches.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request matches http method criteria.
     */
    private function matchRequestMethod(RequestInterface $request)
    {
        return $this->requestMethod == null || $request->requestMethod() == $this->requestMethod;
    }

    /**
     * Checks if the request URI matches.
     *
     * Subclasses implement this method to define the URI matching strategy.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if the request matches URI criteria.
     */
    abstract protected function matchRequestUri(RequestInterface $request);
}
