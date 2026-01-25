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
    public static function matchAny(int $httpCode, array $httpCodes): bool
    {
        foreach ($httpCodes as $code) {
            if (new self($code)->match($httpCode)) {
                return true;
            }
        }
        return false;
    }

    public function __construct($target)
    {
        $this->target = $target;
    }

    public function match(int $httpCode): bool
    {
        $target = (string)$this->target;
        $codeStr = (string)$httpCode;
        if (strpos($target, 'x') !== false) {
            // Replace 'x' with '\d' for regex matching
            $pattern = '/^' . str_replace('x', '\\d', preg_quote($target, '/')) . '$/';
            return preg_match($pattern, $codeStr) === 1;
        }
        return $codeStr === $target;
    }
}
