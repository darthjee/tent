# CacheDirResolver and CacheDirCleaner: refuse unsafe paths up front
`CacheDirResolver`: in `resolve()` and `resolveExact()`, return `null` when `FileUtils::hasSafeSegments($path)` is false, before building any path. Change `resolveExact()`'s return type to `?string` and document the `null` case in both docblocks (defence-in-depth: the cleaner checks first).

`CacheDirCleaner`: at the start of `clean()` and `cleanPath()`, when `FileUtils::hasSafeSegments($path)` is false, log `Logger::warn('cache cleanup refused — path: <path>, reason: unsafe path')` and skip the deletion; the orphan sweep keeps running as it does for every other no-op. `cleanPath()` must also handle a `null` from `resolveExact()` the way `clean()` already handles `null` from `resolve()`. Update the docblocks.

Tests:
- `CacheDirResolverResolveTest.php` / `CacheDirResolverResolveExactTest.php`: `collection`, `entity` and exact resolution of `/a/../b`, `/../x`, `/a/.` and a NUL-byte path return `null`; safe look-alikes still resolve.
- `CacheDirCleanerCleanTest.php` / `CacheDirCleanerCleanPathTest.php`: an unsafe path logs the `warn` with `reason: unsafe path`, deletes nothing inside or outside the root (plant a directory at the traversal target), and leaves the cache untouched.

## Files to Change
- `source/source/lib/content/CacheDirResolver.php` — unsafe-path `null`, `resolveExact(): ?string`, docblocks.
- `source/source/lib/content/CacheDirCleaner.php` — up-front check in `clean()`/`cleanPath()`, `null` handling in `cleanPath()`, docblocks.
- `source/tests/unit/lib/content/CacheDirResolver/CacheDirResolverResolveTest.php` — new specs.
- `source/tests/unit/lib/content/CacheDirResolver/CacheDirResolverResolveExactTest.php` — new specs.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanTest.php` — new specs.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanPathTest.php` — new specs.
