<?php

namespace Tent\Content;

/**
 * Immutable marker of a response served from cache.
 *
 * It holds the {@see FileCache} entry which was served, along with the
 * {@see HeaderFilter} of the {@see \Tent\Middlewares\FileCacheMiddleware} that
 * served it, so that a later rewrite of the same entry (e.g. a background
 * refresh) keeps exactly the headers the original cache write would keep.
 *
 * Recorded on a {@see \Tent\Models\ProcessingRequest} on a cache hit, and used by
 * {@see \Tent\Middlewares\CacheStalenessMiddleware} to tell a cache-served
 * response from any other one.
 */
class CacheHit
{
    /**
     * @var FileCache The cache entry which was served.
     */
    private FileCache $cache;

    /**
     * @var HeaderFilter The header filter of the middleware which served the entry.
     */
    private HeaderFilter $headerFilter;

    /**
     * Constructs a CacheHit.
     *
     * @param FileCache    $cache        The cache entry which was served.
     * @param HeaderFilter $headerFilter The header filter of the middleware which served the entry.
     */
    public function __construct(FileCache $cache, HeaderFilter $headerFilter)
    {
        $this->cache = $cache;
        $this->headerFilter = $headerFilter;
    }

    /**
     * Returns the cache entry which was served.
     *
     * @return FileCache
     */
    public function cache(): FileCache
    {
        return $this->cache;
    }

    /**
     * Returns the header filter of the middleware which served the entry.
     *
     * @return HeaderFilter
     */
    public function headerFilter(): HeaderFilter
    {
        return $this->headerFilter;
    }
}
