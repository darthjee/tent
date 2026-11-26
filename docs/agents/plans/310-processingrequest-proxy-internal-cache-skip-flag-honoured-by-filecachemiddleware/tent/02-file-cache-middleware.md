# FileCacheMiddleware: honour the flag
Add `if ($request->cacheSkipped()) { return true; }` as the **first** statement of `shouldSkipCache(RequestInterface $request)`, before the `skipCacheHeader === null` check. Because `processRequest()` calls `shouldSkipCache()` before matchers and the read-time `evictOn` branch, and `shouldSkipCacheWrite()` ORs `shouldSkipCache($response->request())` before the write-time `evictOn` branch, this single insertion gates read, write and both eviction paths. Update the method's docblock (it is no longer header-only).

Update the class docblock: next to `skip_cache_header`, document that a middleware can force a full bypass in-process with `$request->skipCache()` (no read, no write, no eviction, no header), and that on a `default_proxy` handler the guard must be declared in `prependMiddlewares` for the read path to be bypassed.

Tests:
- `FileCacheMiddlewareProcessRequestTest.php`: flag set + entry on disk → `hasResponse()` is `false`, no cache-hit log; flag set → entry untouched even when matchers would pass; flag set + `evictOn: ['4xx']` + an entry whose stored status is 404 → the entry is still on disk; flag set on a middleware **without** `skip_cache_header` still skips.
- `FileCacheMiddlewareProcessResponseTest.php`: flag set (on the `ProcessingRequest` attached to the response) → cacheable 2xx not stored; pre-existing entry keeps body and `timestamp`; with `require_cache_header` configured nothing is stored and no header is added; `evictOn: ['4xx']` + pre-existing entry + 401 response → entry still on disk; a response whose `request()` is the bare `Request` fallback behaves exactly as today.
- Existing specs must pass unchanged (flag unset).

## Files to Change
- `source/source/lib/middlewares/FileCacheMiddleware.php` — flag-first check in `shouldSkipCache()`; class and method docblocks.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php` — read/eviction specs.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` — write/eviction specs.
