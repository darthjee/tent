<?php

namespace Tent\Utils;

class HttpCodeMatcher
{
    private $target;

    /**
     * Checks if the given HTTP code matches any in the provided list.
     *
     * @param int   $httpCode  The HTTP status code to check.
     * @param array $httpCodes The list of allowed HTTP status codes.
     * @return bool True if the code matches, false otherwise.
     */
    public static function match(int $httpCode, array $httpCodes): bool
    {
        foreach ($httpCodes as $code) {
            if (new self($code)->matches($httpCode)) {
                return true;
            }
        }
        return false;
    }

    public function __construct($target)
    {
        $this->target = $target;
    }

    public function matches(int $httpCode): bool
    {
        return (string)$httpCode === (string)$this->target;
    }
}
