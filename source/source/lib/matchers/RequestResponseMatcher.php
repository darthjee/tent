<?php

namespace Tent\Matchers;

use Tent\Models\RequestInterface;
use Tent\Models\Response;

/**
 * Matcher that checks if a Request or Response matches certain criteria.
 */
abstract class RequestResponseMatcher
{
    /**
     * Checks if the given response matches the criteria.
     * 
     * By default, response matching is not constrained for request matchers, so this method returns true.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        return true;
    }

    /**
     * Checks if the given request matches the criteria.
     * 
     * By default, request matching is not constrained for response matchers, so this method returns true.
     *
     * @param RequestInterface $request The request to check.
     * @return boolean True if the request matches, false otherwise.
     */
    public function matchRequest(RequestInterface $request): bool
    {
        return true;
    }
    /**
     * Builds a RequestResponseMatcher from the given parameters.
     *
     * @param array $params The parameters for building the matcher.
     * @return RequestResponseMatcher The constructed matcher.
     */
    public static function build(array $params): self
    {
        $class = $params['class'];

        return $class::build($params);
    }

    /**
     * Builds an array of RequestResponseMatchers from the given attributes.
     *
     * @param array $attributes The array of attributes for building matchers.
     * @return RequestResponseMatcher[] The array of constructed matchers.
     */
    public static function buildMatchers(array $attributes): array
    {
        $matchers = [];
        foreach ($attributes as $attributes) {
            $matchers[] = self::build($attributes);
        }
        return $matchers;
    }
}
