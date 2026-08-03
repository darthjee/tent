<?php

namespace Tent\Cache;

use Tent\Models\RequestInterface;

/**
 * Default RequestHasher implementation.
 *
 * Preserves Tent's historical cache-key behavior: the hash is derived solely
 * from the request's query string, hashed with SHA-256.
 */
class QueryRequestHasher implements RequestHasher
{
    /**
     * Computes the cache-key hash from the request's query string.
     *
     * @param RequestInterface $request The request to derive the hash from.
     * @return string The SHA-256 hash of the request's query string.
     */
    public function hash(RequestInterface $request): string
    {
        return hash('sha256', $request->query());
    }

    /**
     * Builds a QueryRequestHasher instance. Ignores `$params`.
     *
     * @param array $params Unused.
     * @return self The constructed hasher.
     */
    public static function build(array $params): self
    {
        return new self();
    }
}
