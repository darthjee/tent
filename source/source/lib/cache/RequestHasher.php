<?php

namespace Tent\Cache;

use Tent\Models\RequestInterface;

/**
 * Contract for pluggable cache-key hash generators.
 *
 * A `RequestHasher` is responsible for deriving a filesystem-safe digest
 * from a request, used by {@see \Tent\Content\FileCache} to name the
 * body/meta cache files for that request.
 *
 * Implementations receive the full {@see RequestInterface} (including
 * headers), and are fully responsible for producing a filesystem-safe
 * string and for hashing (e.g. SHA-256) any sensitive request data
 * themselves — Tent performs no sanitization or validation of the
 * returned value.
 */
interface RequestHasher
{
    /**
     * Computes the cache-key hash for the given request.
     *
     * @param RequestInterface $request The request to derive the hash from.
     * @return string The computed hash.
     */
    public function hash(RequestInterface $request): string;

    /**
     * Builds a RequestHasher instance from the given parameters.
     *
     * @param array $params The parameters for building the hasher.
     * @return self The constructed hasher.
     */
    public static function build(array $params): self;
}
