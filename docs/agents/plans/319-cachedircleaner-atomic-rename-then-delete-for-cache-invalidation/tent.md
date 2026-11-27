# Plan: CacheDirCleaner: atomic rename-then-delete for cache invalidation

Issue: [319-cachedircleaner-atomic-rename-then-delete-for-cache-invalidation.md](../../issues/319-cachedircleaner-atomic-rename-then-delete-for-cache-invalidation.md)

## Overview
Make cache invalidation atomic for readers and contention-free between cleaners. `CacheDirCleaner::deleteDir()` first `rename()`s the resolved `<base>/<path>/GET` directory into `<base>/.tent-trash/<unix-ts>.<16 hex>`, then deletes that private tree with the existing #316 walk (`SafeRemover` + bounded restart). A lost rename race is treated as already gone. A real rename failure logs a `warn` and falls back to today's in-place deletion. Each cleaner call then sweeps at most one orphan older than 10 minutes, claimed by rename. A reserved-path guard, applied in `FileCache` and in the cleaner to the lexically normalized resolved path and compared case-insensitively, makes the trash unreachable as cache.

## Context
- Cache layout: `FileCache` stores entries at `<base>/<request path>/<METHOD>/<hash>.{body.dat,meta.json}` (`FileCache::basePath()` → `FileUtils::getFullPath()`, which only trims slashes). `CacheDirResolver` resolves cleanup targets to `<base>/<path>/GET` the same way. Segments are only filtered for emptiness, so `.`/`..` survive.
- `CacheDirCleaner` (source/source/lib/content/CacheDirCleaner.php) today: `deleteDir()` scope-checks with `str_starts_with($dir, $base . '/')`, unlinks a symlinked root, ignores a non-directory, otherwise runs `removeDirRecursive()` (3 walk attempts, `createIterator()` test seam), and logs `cache cleared — dir: <dir>` at `debug` when the result is `RemovalResult::Removed`.
- Classes are loaded by explicit `require_once` lines in `source/source/loader.php`. Any new class must be registered there, before its first consumer.
- Every design decision (naming, orphans, rename failure, symlinks, edge cases, compatibility, tests) is settled in the issue. The steps below implement it as written.
- Out of scope: `FileCache::remove()` semantics beyond the guard, the pre-existing path traversal (#322), deferring the walk after the response, and multi-process stress tests.

## Steps

- [01 — Path normalization and the CacheTrash helper](tent/01-cache-trash-helper.md)
- [02 — Reserved-path guard in FileCache](tent/02-filecache-guard.md)
- [03 — Rename-then-delete in CacheDirCleaner](tent/03-rename-then-delete.md)
- [04 — Bounded orphan sweep](tent/04-orphan-sweep.md)
- [05 — Documentation](tent/05-docs.md)

## CI Checks
- `source`: `composer coverage` (PHPUnit with coverage, CI job running `composer coverage` for the `source` folder)
- `source`: `composer lint` (phpcs + phpmd, CI job running `composer lint` for the `source` folder)

## Notes
- **Risk: `rename()` onto an existing empty directory silently replaces it on POSIX.** The `random_bytes` suffix makes this practically impossible. Do not add a pre-check `file_exists` on the target, because it would be racy anyway.
- **Never remove `.tent-trash` itself**, even when it's empty. The sweep only deletes entries inside it.
- **The `cache cleared` line must keep logging the original resolved path**, so the existing assertions in `CacheDirCleanerCleanTest`, `CacheDirCleanerCleanPathTest` and `CacheCleanupMiddlewareProcessRequestTest` keep holding unchanged. If any of them fails, the implementation is wrong, not the test.
- **Keep the never-throws contract everywhere.** `@rename`/`@mkdir` failures are classified with `clearstatcache` + `file_exists`/`is_link`, and the reason comes from `error_get_last()`.
- PHPMD/phpcs limits (method length, complexity) may push the sweep and rename logic into small private methods. That's expected.
