<?php

namespace Tent\Content;

use Tent\Log\Logger;
use Tent\Models\Response;

/**
 * Allow-list `HeaderFilter`: keeps only headers whose name matches the configured
 * list, stripping everything else.
 *
 * Header name matching is case-insensitive and compares the exact header name only
 * (never a substring match) — each raw `"Header-Name: value"` line is split on the
 * **first** colon only, since values (e.g. `Expires` dates) can themselves contain
 * colons. A response carrying more than one header with the same name (e.g. multiple
 * `Set-Cookie` lines) has every matching line kept, not just the first.
 *
 * Unlike `ExcludedHeaderFilter`, there is no built-in default list — callers
 * configuring `mode: 'allow'` must provide an explicit, complete list of the only
 * headers they want cached.
 *
 * ## Example
 *
 * ```php
 * $filter = new AllowedHeaderFilter(['Content-Type', 'Cache-Control']);
 * $filtered = $filter->filter($response);
 * ```
 */
class AllowedHeaderFilter implements HeaderFilter
{
    /**
     * @var array<string, bool> Lowercased hash-set of allowed header names.
     */
    private array $allowedHeaders;

    /**
     * @param array $allowedHeaders The explicit, complete list of header names to
     *                               keep in cache storage.
     */
    public function __construct(array $allowedHeaders)
    {
        $this->allowedHeaders = array_change_key_case(array_flip($allowedHeaders), CASE_LOWER);
    }

    /**
     * Returns a clone of the response with every non-allowed header stripped.
     *
     * @param Response $response The response to filter.
     * @return Response A clone of $response with only the allowed headers kept.
     */
    public function filter(Response $response): Response
    {
        $kept = [];
        $stripped = [];

        foreach ($response->headers() as $headerLine) {
            [$name] = explode(':', $headerLine, 2);
            $name = trim($name);

            if (isset($this->allowedHeaders[strtolower($name)])) {
                $kept[] = $headerLine;
            } else {
                $stripped[] = $name;
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
