<?php

namespace Tent\Utils;

/**
 * Utility class for matching HTTP status codes against a list of allowed codes,
 * including support for wildcards like '4xx' or '30x'.
 */
class HttpCodeMatcher
{
    /**
     * @var string|int The target HTTP status code or pattern to match against.
     */
    private $target;

    /**
     * Checks if the given HTTP code matches any in the provided list.
     *
     * @param int   $httpCode  The HTTP status code to check.
     * @param array $httpCodes The list of allowed HTTP status codes.
     * @return bool True if the code matches, false otherwise.
     *
     * @example
     *   HttpCodeMatcher::matchAny(404, ['2xx', '404', '500']) // returns true
     *   HttpCodeMatcher::matchAny(201, ['4xx', '500'])        // returns false
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

    /**
     * Constructs an HttpCodeMatcher with the given target code or pattern.
     *
     * @param string|int $target The target HTTP status code or pattern.
     */
    public function __construct($target)
    {
        $this->target = $target;
    }

    /**
     * Checks if the given HTTP code matches the target code or pattern.
     *
     * @param int $httpCode The HTTP status code to check.
     * @return bool True if the code matches, false otherwise.
     */
    public function match(int $httpCode): bool
    {
        $target = (string)$this->target;
        $codeStr = (string)$httpCode;
        if (preg_match('/[xX]/', $target)) {
            // Replace both 'x' and 'X' with '\d' for regex matching
            $pattern = '/^' . str_replace(['x', 'X'], '\\d', preg_quote($target, '/')) . '$/';
            return preg_match($pattern, $codeStr) === 1;
        }
        return $codeStr === $target;
    }
}
