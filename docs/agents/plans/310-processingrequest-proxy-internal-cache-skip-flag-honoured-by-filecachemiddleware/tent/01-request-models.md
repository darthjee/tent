# Request models: the flag
Declare `skipCache(): void` and `cacheSkipped(): bool` on `RequestInterface` with docblocks stating that the flag is proxy-internal, one-way, never serialized into a header, and honoured by the cache middlewares.

Implement them on `ProcessingRequest` with a private `bool $cacheSkipped = false` (next to the existing `$cacheHash` state, mirroring `cacheHash()` / `setCacheHash()`). `skipCache()` only ever sets `true`; there is no clear method, no setter taking a bool, and the property is **not** added to `ATTRIBUTES`.

Implement them on `Request` as a no-op `skipCache()` and a `cacheSkipped()` that always returns `false` — a bare `Request` (e.g. the `Response::request()` fallback) must never claim a cache decision.

Tests:
- New `ProcessingRequestCacheSkipTest.php`: `false` with no params; `false` with unrelated params (e.g. `requestPath`, `headers`); `true` after `skipCache()`; still `true` after two calls; `headers()` unchanged after `skipCache()` (no marker header).
- Extend `RequestTest.php`: `cacheSkipped()` is `false`, and stays `false` after `skipCache()`.

## Files to Change
- `source/source/lib/models/RequestInterface.php` — declare the two methods.
- `source/source/lib/models/ProcessingRequest.php` — private flag + both methods.
- `source/source/lib/models/Request.php` — no-op / always-false implementations.
- `source/tests/unit/lib/models/ProcessingRequest/ProcessingRequestCacheSkipTest.php` — new spec.
- `source/tests/unit/lib/models/RequestTest.php` — `Request` behaviour.
