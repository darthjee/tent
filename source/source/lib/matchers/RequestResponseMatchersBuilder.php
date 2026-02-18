<?php

namespace Tent\Matchers;

use Tent\Utils\Logger;

/**
 * Builder class for constructing request-response matchers based on configuration attributes.
 *
 * ⚠️ TEMPORARY CLASS - This class exists only to support the transition from the deprecated 'httpCodes'
 * attribute to the new 'matchers' attribute. Once all deprecated code is removed and users have migrated
 * to using 'matchers', this entire class will be deleted. Once that happens, we won't need this extra
 * complication - matcher instantiation will be straightforward and direct.
 *
 * Currently handles both the deprecated 'httpCodes' attribute and the new 'matchers' attribute
 * to allow for a smooth transition while providing flexibility in defining matchers.
 */
class RequestResponseMatchersBuilder
{
    /**
     * Deprecation warning message for httpCodes attribute.
     */
    private const DEPRECATION_HTTP_CODES_MSG =
      'Deprecation warning: The "httpCodes" attribute is deprecated. Use "matchers" instead.';

    /**
     * The configuration attributes for building matchers.
     */
    private array $attributes;

    /**
     * Constructs a RequestResponseMatchersBuilder instance with the given attributes.
     * @param array $attributes The configuration attributes for building matchers.
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Builds an array of request-response matchers based on the provided attributes.
     *
     * This method checks for the presence of the 'matchers' attribute to build custom matchers.
     * If 'matchers' is not provided, it falls back to the deprecated 'httpCodes' attribute to create
     * a StatusCodeMatcher. If neither is provided, it defaults to caching responses with a 200 status code.
     *
     * Example usage:
     * ```php
     * $attributes = [
     *   'matchers' => [
     *     [
     *       'class' => 'StatusCodeMatcher',
     *       'httpCodes' => [200, 201]
     *     ]
     *   ]
     * ];
     * $matchers = (new RequestResponseMatchersBuilder($attributes))->build();
     * ```
     *
     * @return array The array of constructed matchers.
     */
    public function build(): array
    {
        if (isset($this->attributes['httpCodes'])) {
            Logger::deprecate(self::DEPRECATION_HTTP_CODES_MSG);
        }

        if (isset($this->attributes['matchers'])) {
            $matchers = RequestResponseMatcher::buildMatchers($this->attributes['matchers']);
        } elseif (isset($this->attributes['httpCodes'])) {
            $httpCodes = $this->attributes['httpCodes'] ?? [200];
            $matchers = [new StatusCodeMatcher($httpCodes)];
        } else {
            $matchers = [new StatusCodeMatcher([200])];
        }

        return $matchers;
    }
}
