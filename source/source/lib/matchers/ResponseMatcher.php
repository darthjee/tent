<?php

namespace Tent\Matchers;

use Tent\Models\Response;

/**
 * Matcher that checks if a Response matches certain criteria.
 */
abstract class ResponseMatcher
{
    /**
     * Checks if the given response matches the criteria.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     */
    abstract public function match(Response $response): bool;

    /** */
     * Builds a ResponseMatcher from the given parameters.
     *
     * @param array $params The parameters for building the matcher.
     * @return ResponseMatcher The constructed ResponseMatcher.
     */
    public static function build(array $params): self
    {
        $class = $params['class'];

        return $class::build($params);
    }
}
