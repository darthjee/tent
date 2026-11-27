# Plan: CacheDirCleaner: atomic rename-then-delete for cache invalidation

Issue: [319-cachedircleaner-atomic-rename-then-delete-for-cache-invalidation.md](../../issues/319-cachedircleaner-atomic-rename-then-delete-for-cache-invalidation.md)

## Overview
`CacheDirCleaner::deleteDir()` atomically renames the resolved cache directory into a permanent `<base>/.tent-trash/` folder before deleting it privately with the #316 machinery, with an in-place fallback, a bounded orphan sweep, and a reserved-path guard in `FileCache` and the cleaner. All work is inside `source/` (plus the related docs) and owned by the `tent` specialist.

See [tent.md](tent.md) for the full plan.
