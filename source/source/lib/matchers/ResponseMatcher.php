<?php

namespace Tent\Matchers;

use Tent\Models\Response;

/**
 * Matcher that checks if a Response matches certain criteria.
 *
 * @deprecated Use Filter instead. ResponseMatcher will be removed in a future version.
 */
abstract class ResponseMatcher extends Filter
{
    /**
     * Checks if the given response matches the criteria.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     * @deprecated Use matchResponse() instead.
     */
    abstract public function match(Response $response): bool;

    /**
     * Implements the Filter interface by delegating to match().
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        return $this->match($response);
    }

    /**
     * Builds a ResponseMatcher from the given parameters.
     *
     * @param array $params The parameters for building the matcher.
     * @return ResponseMatcher The constructed ResponseMatcher.
     * @deprecated Use Filter::build() instead.
     */
    public static function build(array $params): self
    {
        $class = $params['class'];

        return $class::build($params);
    }

    /**
     * Builds an array of ResponseMatchers from the given attributes.
     *
     * @param array $attributes The array of attributes for building matchers.
     * @return ResponseMatcher[] The array of constructed ResponseMatchers.
     * @deprecated Use Filter::buildFilters() instead.
     */
    public static function buildMatchers(array $attributes): array
    {
        $matchers = [];
        foreach ($attributes as $matcherConfig) {
            $matchers[] = self::build($matcherConfig);
        }
        return $matchers;
    }
}
