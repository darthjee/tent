# Add CacheHit marker

Create the immutable value object `Tent\Content\CacheHit` holding the served `FileCache` entry and the `HeaderFilter` of the `FileCacheMiddleware` that served it (`cache()`, `headerFilter()`). Add `setCacheHit(CacheHit $hit): CacheHit` and `cacheHit(): ?CacheHit` to `ProcessingRequest` (private `?CacheHit $cacheHit = null`), with docblocks explaining it marks a response served from cache and is used by `CacheStalenessMiddleware` for provenance. Do **not** add these methods to `RequestInterface`.

Unit tests: `CacheHit` accessors; `ProcessingRequest::cacheHit()` is null by default and returns the set value.

## Files to Change
- `source/source/lib/content/CacheHit.php` — new value object.
- `source/source/lib/models/ProcessingRequest.php` — marker property and accessors.
- `source/source/loader.php` — `require_once` for `CacheHit.php`.
- `source/tests/unit/lib/content/CacheHitTest.php` — new.
- `source/tests/unit/lib/models/ProcessingRequest*Test.php` — marker accessor tests (follow the existing test file layout).
