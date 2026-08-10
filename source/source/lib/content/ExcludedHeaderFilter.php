<?php

namespace Tent\Content;

use Tent\Log\Logger;
use Tent\Models\Response;

/**
 * Deny-list `HeaderFilter`: strips every header whose name matches the configured
 * list, keeping everything else.
 *
 * Header name matching is case-insensitive and compares the exact header name only
 * (never a substring match) — each raw `"Header-Name: value"` line is split on the
 * **first** colon only, since values (e.g. `Expires` dates) can themselves contain
 * colons. A response carrying more than one header with the same name (e.g. multiple
 * `Set-Cookie` lines) has every matching line dropped, not just the first.
 *
 * ## Example
 *
 * ```php
 * $filter = new ExcludedHeaderFilter(ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS);
 * $filtered = $filter->filter($response);
 * ```
 */
class ExcludedHeaderFilter implements HeaderFilter
{
    /**
     * Curated list of headers excluded from cache storage by default.
     *
     * - `Set-Cookie`/`Set-Cookie2`: leak one client's session cookie to every future
     *   cache hit.
     * - `WWW-Authenticate`/`Proxy-Authenticate`: carry auth-challenge/realm details
     *   tied to the original request context.
     */
    public const DEFAULT_EXCLUDED_HEADERS = ['Set-Cookie', 'Set-Cookie2', 'WWW-Authenticate', 'Proxy-Authenticate'];

    /**
     * @var array<string, bool> Lowercased hash-set of excluded header names.
     */
    private array $excludedHeaders;

    /**
     * @param array $excludedHeaders The already-resolved, already-merged list of header
     *                                names to strip from cache storage.
     */
    public function __construct(array $excludedHeaders)
    {
        $this->excludedHeaders = array_change_key_case(array_flip($excludedHeaders), CASE_LOWER);
    }

    /**
     * Returns a clone of the response with every excluded header stripped.
     *
     * @param Response $response The response to filter.
     * @return Response A clone of $response with the excluded headers removed.
     */
    public function filter(Response $response): Response
    {
        $kept = [];
        $stripped = [];

        foreach ($response->headers() as $headerLine) {
            [$name] = explode(':', $headerLine, 2);
            $name = trim($name);

            if (isset($this->excludedHeaders[strtolower($name)])) {
                $stripped[] = $name;
            } else {
                $kept[] = $headerLine;
            }
        }

        if ($stripped !== []) {
            Logger::debug('[cache] - stripped headers from cache write: ' . implode(', ', $stripped));
        }

        $filtered = clone $response;
        $filtered->setHeaders($kept);

        return $filtered;
    }
}
