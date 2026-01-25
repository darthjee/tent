<?php

namespace Tent\Utils;

class HttpCodeMatcher
{
    private array $httpCodes;

    /**
     * Checks if the given HTTP code matches any in the provided list.
     *
     * @param int   $httpCode  The HTTP status code to check.
     * @param array $httpCodes The list of allowed HTTP status codes.
     * @return bool True if the code matches, false otherwise.
     */
    public static function match(int $httpCode, array $httpCodes): bool
    {
        return new self($httpCodes)->matches($httpCode);
    }

    public function __construct(array $httpCodes)
    {
        $this->httpCodes = $httpCodes;
    }

    public function matches(int $httpCode): bool
    {
        foreach ($this->httpCodes as $code) {
            if ($this->matchHttpCode($httpCode, $code)) {
                return true;
            }
        }
        return false;
    }

    protected function matchHttpCode(int $code, $target): bool
    {
        return (string)$code === (string)$target;
    }
}
