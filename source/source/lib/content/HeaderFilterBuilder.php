<?php

namespace Tent\Content;

/**
 * Shared `(mode, excluded_headers, additional_excluded_headers, allowed_headers)` →
 * `HeaderFilter` resolution helper, used identically by `Tent\Middlewares\FileCacheMiddleware`
 * and `Tent\Middlewares\CacheStalenessMiddleware` so both write paths stay in sync.
 *
 * ## Deny mode (`mode === 'deny'`, the default)
 *
 * The excluded-headers list is `$excludedHeaders ?? ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS`,
 * with `$additionalExcludedHeaders` always merged on top (regardless of whether
 * `$excludedHeaders` was passed).
 *
 * ## Allow mode (`mode === 'allow'`)
 *
 * `$allowedHeaders` must be a non-empty list — there is no default and no append
 * option, since an empty allow-list would silently strip every header.
 *
 * Any other `$mode` value throws `\InvalidArgumentException`, mirroring
 * `Tent\Middlewares\FilterQueryParamsMiddleware`'s existing `mode` guard.
 */
class HeaderFilterBuilder
{
    /**
     * Builds the `HeaderFilter` matching the given mode/list configuration.
     *
     * @param string     $mode                      Either 'deny' or 'allow'.
     * @param array|null $excludedHeaders           Deny mode: full override of the excluded
     *                                               list. Defaults to `DEFAULT_EXCLUDED_HEADERS`
     *                                               when omitted.
     * @param array|null $additionalExcludedHeaders Deny mode: always merged on top of the
     *                                               resolved excluded list.
     * @param array|null $allowedHeaders            Allow mode: explicit, complete list of the
     *                                              only headers to keep. Required, non-empty.
     * @return HeaderFilter
     * @throws \InvalidArgumentException If $mode is invalid, or 'allow' mode has an empty/missing list.
     */
    public static function build(
        string $mode,
        ?array $excludedHeaders,
        ?array $additionalExcludedHeaders,
        ?array $allowedHeaders
    ): HeaderFilter {
        if ($mode === 'deny') {
            $resolved = array_merge(
                $excludedHeaders ?? ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS,
                $additionalExcludedHeaders ?? []
            );

            return new ExcludedHeaderFilter($resolved);
        }

        if ($mode === 'allow') {
            if (empty($allowedHeaders)) {
                throw new \InvalidArgumentException(
                    "Invalid 'allowed_headers': an empty allow-list has no legitimate use, " .
                    "it would silently strip every header"
                );
            }

            return new AllowedHeaderFilter($allowedHeaders);
        }

        throw new \InvalidArgumentException("Invalid mode '{$mode}', expected 'allow' or 'deny'");
    }
}
