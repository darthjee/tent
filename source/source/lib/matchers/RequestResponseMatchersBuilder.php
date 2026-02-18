<?php

namespace Tent\Matchers;

use Tent\Utils\Logger;

class RequestResponseMatchersBuilder
{
    /**
     * Deprecation warning message for httpCodes attribute.
     */
    private const DEPRECATION_HTTP_CODES_MSG =
      'Deprecation warning: The "httpCodes" attribute is deprecated. Use "matchers" instead.';
      
    public function build(array $attributes): array
    {
        if (isset($attributes['httpCodes'])) {
            Logger::deprecate(self::DEPRECATION_HTTP_CODES_MSG);
        }

        if (isset($attributes['matchers'])) {
            $matchers = RequestResponseMatcher::buildMatchers($attributes['matchers']);
        } elseif (isset($attributes['httpCodes'])) {
            $httpCodes = $attributes['httpCodes'] ?? [200];
            $matchers = [new StatusCodeMatcher($httpCodes)];
        } else {
            $matchers = [new StatusCodeMatcher([200])];
        }

        return $matchers;
    }
}