# FileCacheMiddleware read/write policy
Append `?array $evictOn = null` to the `FileCacheMiddleware` constructor and `build()` (same key spellings), default `[]`. Read path (`processRequest()`): when the entry exists and `FileCache::httpCode()` matches the policy, call `remove()` and return the request unmodified so the handler runs. Write path (`processResponse()`): before `isCacheable()`, if the response status matches, remove any existing entry for the key and skip the write.

## Files to Change
- `source/source/lib/middlewares/FileCacheMiddleware.php` — option, property, build parsing, two enforcement points, docblock.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` — default disabled, override, empty list.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php` — evicted 4xx treated as a miss, 200 served, files removed, default policy serves stored 4xx.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` — 4xx not stored, existing entry removed, 200 stored, disabled policy stores 4xx.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareSymmetryTest.php` — a 4xx evicted on write is absent on the next read.
