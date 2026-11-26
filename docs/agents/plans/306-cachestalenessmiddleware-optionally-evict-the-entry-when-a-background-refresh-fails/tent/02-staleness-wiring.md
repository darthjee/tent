# CacheStalenessMiddleware and BackgroundRefresher wiring
Append `?array $evictOn = null` as the last `CacheStalenessMiddleware` constructor parameter; `build()` reads `evictOn` then `evict_on` (camel wins). Store `EvictionPolicy::build($evictOn)`. Pass it as a new last argument to `BackgroundRefresher` in `scheduleRefresh()`. In `BackgroundRefresher::refresh()` add the branch: success → `replaceCache()`, policy match → new `evictCache()` (`cache->remove()` + warn log with code), else `keepStaleCache()`. Exceptions still go to `handleFailure()` and never evict. Update both class docblocks.

## Files to Change
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — option, property, build parsing, pass-through, docblock.
- `source/source/lib/service/BackgroundRefresher.php` — constructor param (optional, defaults to an empty policy so existing callers keep working), `evictCache()`, docblock.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php` — build cases from the issue.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — eviction, keep, wildcard, exception, sentinel cleanup, debounce, log, custom hasher cases.
- `source/tests/unit/lib/service/BackgroundRefresherTest.php` — extend with success/evict/keep/exception/header-filter cases.
