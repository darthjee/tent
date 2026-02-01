<?php

namespace Tent\Models\ResponseMatchers;

use Tent\Models\Response;

/**
 * Matcher that checks if a Response matches certain criteria.
 */
interface ResponseMatcher
{
    /**
     * Checks if the given response matches the criteria.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     */
    public function match(Response $response): bool;
}
