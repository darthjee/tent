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

    private array $attributes;

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

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