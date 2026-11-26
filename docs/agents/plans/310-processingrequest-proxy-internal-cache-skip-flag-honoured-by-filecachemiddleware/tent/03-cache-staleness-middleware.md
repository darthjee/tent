# CacheStalenessMiddleware: guard
Add `if ($request->cacheSkipped()) { return $request; }` as the first line of `processRequest()`, before the `hasResponse()` check, so no `FileCache` timestamp is read, no `.refreshing` sentinel is written and no `BackgroundRefresher` is built — neither `replaceCache()` nor `evictCache()` can run for a skipped request, whatever `evictOn` is configured. Document the guard in the class/method docblock, keeping the guarantee local to this class rather than dependent on chain wiring.

Tests (unit level, calling `processRequest()` directly with a pre-set stale response, as existing specs do):
- flag set → the injected scheduler callable is never invoked and no `.refreshing` sentinel exists afterwards;
- same with `evictOn: ['4xx']` configured → no refresh scheduled and the cache entry is still on disk;
- existing refresh-scheduling specs pass unchanged.

## Files to Change
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — first-line guard; docblock.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — guard specs.
