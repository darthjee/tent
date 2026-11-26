<?php

namespace Tent\Content;

use Tent\Utils\HttpCodeMatcher;

/**
 * Decides whether a cache entry must be evicted for a given HTTP status code.
 *
 * Codes can be exact integers (e.g. 404) or wildcard patterns (e.g. '4xx').
 */
class EvictionPolicy
{
    /**
     * @var HttpCodeMatcher[]
     */
    private array $matchers = [];

    /**
     * @param array $codes List of integers or patterns.
     * @throws \InvalidArgumentException When an entry is invalid.
     */
    public function __construct(array $codes = [])
    {
        foreach ($codes as $code) {
            self::validateEntry($code);
            $this->matchers[] = new HttpCodeMatcher($code);
        }
    }

    /**
     * Builds a policy from configuration.
     *
     * @param array|null $codes   Configured codes, or null to use the default.
     * @param array      $default Default codes when $codes is null.
     * @return EvictionPolicy
     * @throws \InvalidArgumentException When the configuration is invalid.
     */
    public static function build(?array $codes, array $default = []): EvictionPolicy
    {
        return new self($codes ?? $default);
    }

    /**
     * Checks if the given HTTP code must cause eviction.
     *
     * @param integer $httpCode The HTTP status code.
     * @return boolean
     */
    public function evicts(int $httpCode): bool
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->match($httpCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the policy has no codes (never evicts).
     *
     * @return boolean
     */
    public function isEmpty(): bool
    {
        return empty($this->matchers);
    }

    /**
     * @param mixed $code Entry to validate.
     * @return void
     * @throws \InvalidArgumentException When the entry is invalid.
     */
    private static function validateEntry(mixed $code): void
    {
        if (is_int($code) || (is_string($code) && preg_match('/^[0-9xX]{3}$/', $code) === 1)) {
            return;
        }

        throw new \InvalidArgumentException('Invalid eviction code: ' . var_export($code, true));
    }
}
