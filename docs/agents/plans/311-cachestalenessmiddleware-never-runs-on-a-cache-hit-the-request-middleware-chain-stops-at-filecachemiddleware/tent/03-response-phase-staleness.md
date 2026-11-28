# Move staleness check to the response phase

In `CacheStalenessMiddleware`:

- Remove the `processRequest()` override (inherit the pass-through).
- Add `processResponse(Response $response): Response`: return `$response` unchanged unless `$response->request()` is a `ProcessingRequest`, is not `cacheSkipped()`, and has a `cacheHit()`. Read the timestamp from `$hit->cache()`; if null or `age <= maxAgeSeconds`, return. Otherwise keep the existing debug log and `triggerRefresh()` / sentinel / scheduler flow, passing `$hit->cache()` and building the `BackgroundRefresher` with `$hit->headerFilter()` instead of the middleware's own filter. `evictOn` stays the middleware's own.
- Deprecate the header-filter options: `build()` still reads `mode`, `excluded_headers`, `additional_excluded_headers`, `allowed_headers` but ignores them, logging one `Logger::warn` deprecation message (naming the present keys and that `FileCacheMiddleware`'s filter is used) when any is present. Keep the constructor signature; mark the header-filter parameters `@deprecated` and stop using the built `HeaderFilter` (drop the property if nothing else needs it, keeping the parameters).
- Update the class docblock: response-phase behavior, provenance via `CacheHit`, placement flexibility (recommendation unchanged), deprecated header-filter options, and an explicit caveat that on the shipped `php:8.4-apache` (mod_php) image the refresh runs synchronously.

Tests: rewrite `CacheStalenessMiddlewareProcessRequestTest` into a `processResponse` test (rename file accordingly) building requests with a `CacheHit`; cover no marker → no refresh, non-`ProcessingRequest` → no refresh, `cacheSkipped()` → no refresh, fresh entry → no refresh, stale entry → refresh scheduled with the marker's filter, response returned unmodified. Adapt `CacheStalenessMiddlewareSentinelTest` and `CacheStalenessMiddlewareOverlongPathTest` to drive `processResponse()`. Extend `CacheStalenessMiddlewareBuildTest`: deprecated keys accepted, warning logged, no warning without them.

## Files to Change
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — response-phase check, deprecations, docblock.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — becomes a `processResponse` test.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareSentinelTest.php` — drive `processResponse()`.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareOverlongPathTest.php` — drive `processResponse()`.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php` — deprecation tests.
