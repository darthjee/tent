<?php

namespace Tent\Matchers;

use Tent\Models\Response;
use Tent\Models\ProcessingRequest;

/**
 * Filter that checks if a Request or Response matches certain criteria.
 *
 * Filters can be used to determine if middleware operations should be applied
 * based on request or response characteristics.
 */
abstract class Filter
{
    /**
     * Checks if the given request matches the criteria.
     *
     * Default implementation returns true (no filtering on request).
     * Override this method to add request-based filtering.
     *
     * @param ProcessingRequest $request The request to check.
     * @return boolean True if the request matches, false otherwise.
     */
    public function matchRequest(ProcessingRequest $request): bool
    {
        return true;
    }

    /**
     * Checks if the given response matches the criteria.
     *
     * Default implementation returns true (no filtering on response).
     * Override this method to add response-based filtering.
     *
     * @param Response $response The response to check.
     * @return boolean True if the response matches, false otherwise.
     */
    public function matchResponse(Response $response): bool
    {
        return true;
    }

    /**
     * Builds a Filter from the given parameters.
     *
     * @param array $params The parameters for building the filter.
     * @return Filter The constructed Filter.
     */
    public static function build(array $params): self
    {
        $class = $params['class'];

        return $class::build($params);
    }

    /**
     * Builds an array of Filters from the given attributes.
     *
     * @param array $attributes The array of attributes for building filters.
     * @return Filter[] The array of constructed Filters.
     */
    public static function buildFilters(array $attributes): array
    {
        $filters = [];
        foreach ($attributes as $filterConfig) {
            $filters[] = self::build($filterConfig);
        }
        return $filters;
    }
}
