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
     * Constructs an HttpCodeMatcher with the given target code or pattern.
     *
     * @param string|integer $target The target HTTP status code or pattern.
     */
    public function __construct($target)
    {
        $this->target = $target;
    }

    /**
     * Checks if the given HTTP code matches the target code or pattern.
     *
     * @param integer $httpCode The HTTP status code to check.
     * @return boolean True if the code matches, false otherwise.
     */
    public function match(int $httpCode): bool
    {
        $target = (string)$this->target;
        $codeStr = (string)$httpCode;

        if (preg_match('/[xX]/', $target)) {
            return $this->checkByRegExp($httpCode);
        }

        return $codeStr === $target;
    }

    /**
     * Checks if the HTTP code matches the target pattern using regular expressions.
     *
     * @param integer $httpCode The HTTP status code to check.
     * @return boolean True if the code matches the pattern, false otherwise.
     */
    private function checkByRegExp(int $httpCode): bool
    {
        $pattern = $this->regExp();
        return preg_match($pattern, (string)$httpCode) === 1;
    }

    /**
     * Builds the regular expression pattern from the target code or pattern.
     *
     * @return string The regular expression pattern.
     */
    private function regExp(): string
    {
        $target = (string)$this->target;
        return '/^' . str_replace(['x', 'X'], '\\d', preg_quote($target, '/')) . '$/';
    }
}
