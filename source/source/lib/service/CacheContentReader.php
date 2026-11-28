<?php

namespace Tent\Service;

use Tent\Content\FileCache;
use Tent\Models\RequestInterface;

/**
 * Reads a cache hit and returns it as a Response.
 *
 * Unlike {@see ResponseContentReader}, it does not apply
 * {@see \Tent\Validators\RequestPathValidator} to the request path: {@see FileCache}
 * screens paths itself, an unsafe one making the entry inert (never existing).
 * Applying the validator, which rejects any `..` substring or backslash, would
 * break hits for safe paths such as `/a..b` or `/...`.
 *
 * Only a FileCache is accepted, so the path validation cannot be skipped for
 * other content, such as static files.
 *
 * ## Example
 *
 * ```php
 * $cache = new FileCache($request, $location);
 *
 * if ($cache->exists()) {
 *     $response = (new CacheContentReader($request, $cache))->getResponse();
 * }
 * ```
 */
class CacheContentReader extends ResponseContentReader
{
    /**
     * Constructs a CacheContentReader for the given request and cache entry.
     *
     * @param RequestInterface $request The HTTP request containing the request path.
     * @param FileCache        $cache   The cache entry to be read.
     */
    public function __construct(RequestInterface $request, FileCache $cache)
    {
        parent::__construct($request, $cache);
    }

    /**
     * Accepts any request path, as the cache entry has already screened it.
     *
     * @return void
     */
    protected function validateFilePath(): void
    {
        // Intentionally empty: an unsafe path never reaches a read, as its entry does not exist.
    }
}
