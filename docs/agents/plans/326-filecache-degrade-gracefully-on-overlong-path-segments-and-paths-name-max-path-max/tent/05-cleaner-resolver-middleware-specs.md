# Cleaner, resolver, staleness and middleware specs

Cover the other entry points with overlong input. All of these specs use `PhpWarningCatcher` and assert that no warning was raised.

**`CacheDirCleaner` / `CacheDirResolver`**
- `CacheDirResolver::resolve('collection'|'entity', $path)` and `resolveExact($path)`, with a 300-byte segment and with a path longer than 4096 bytes: they return a path or `null` without a warning or exception.
- `CacheDirCleaner::clean(...)` and `cleanPath(...)` with the same inputs: no warning or exception, and the tree is unchanged (existing sibling entries are untouched).
- These are expected to pass as-is (they use `@rename`, `@scandir`, `realpath()` and `file_exists()`). If one fails, fix the offending call in that class (silence it and log through `Logger`, following the existing patterns there).

**`CacheStalenessMiddleware` guard**
- Add a spec asserting that the refresh sentinel suffix fits the `FileCache` headroom: `strlen(REFRESH_SENTINEL_SUFFIX) <= FileCache::DERIVED_SUFFIX_HEADROOM`.
- This needs the constant to be readable: change `private const REFRESH_SENTINEL_SUFFIX` to `public const`, or read it via reflection in the spec. Prefer `public const`.

**`FileCacheMiddleware`** — new `FileCacheMiddlewareOverlongPathTest`, modelled on `FileCacheMiddlewareUnsafePathTest`:
- `processRequest()` with a 300-byte segment is a miss (no response set);
- `processResponse()` returns the response unchanged (same body, headers and status, e.g. 200 with a custom header), writes nothing, and raises no warning.

## Files to Change
- `source/tests/unit/lib/content/CacheDirResolver/CacheDirResolverOverlongPathTest.php` — new spec.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerOverlongPathTest.php` — new spec.
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — make `REFRESH_SENTINEL_SUFFIX` public.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/` — add the headroom guard spec (new file or existing suite).
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareOverlongPathTest.php` — new spec.
- `source/source/lib/content/CacheDirCleaner.php` / `CacheDirResolver.php` — only if a spec shows a warning.
