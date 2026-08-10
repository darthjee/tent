<?php

namespace Tent\Content;

use Tent\Models\Response;

/**
 * Strategy for filtering which headers of a `Response` are kept when it is written
 * to a cache.
 *
 * Implementations never mutate the given `Response` in place — they return a clone
 * with only the `headers` collection replaced, leaving `body`/`httpCode`/`request`
 * untouched. This lets `Tent\Service\ResponseCacher` apply a filter uniformly at the
 * single choke point every cache-write call site already shares, without any write
 * path needing to remember to do it itself.
 *
 * @see ExcludedHeaderFilter Deny-list implementation.
 * @see AllowedHeaderFilter Allow-list implementation.
 * @see HeaderFilterBuilder Shared `(mode, ...)` resolution helper.
 */
interface HeaderFilter
{
    /**
     * Returns a clone of the given response with its headers filtered.
     *
     * @param Response $response The response to filter.
     * @return Response A clone of $response with a filtered `headers` collection.
     */
    public function filter(Response $response): Response;
}
