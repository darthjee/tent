# Set the marker in FileCacheMiddleware

In `FileCacheMiddleware::processRequest()`, when a cached entry is served (`$cache->exists()` and not evicted), call `$request->setCacheHit(new CacheHit($cache, $this->headerFilter))` next to `setResponse()`. No marker on a miss, on eviction-on-read, on matcher mismatch, or when the cache is skipped (`skipCache()` / `skip_cache_header`). Update the method docblock.

Unit tests: marker set on hit with the same `FileCache` path and the middleware's header filter; absent on miss, eviction-on-read, matcher mismatch, and skipped cache.

## Files to Change
- `source/source/lib/middlewares/FileCacheMiddleware.php` — set the marker on hit.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware*` — marker tests (follow existing layout).
