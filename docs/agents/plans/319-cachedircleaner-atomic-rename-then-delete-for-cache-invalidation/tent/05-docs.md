# Documentation

Describe the new behavior where operators and contributors will look for it.

- `docs/agents/architecture.md`: rewrite the `CacheDirCleaner` paragraph (currently line ~115) to cover:
  - rename into `<base>/.tent-trash/<ts>.<random>`, then the private delete;
  - a lost race counting as already gone;
  - `warn` plus in-place fallback on a real rename failure or an unusable trash;
  - the bounded orphan sweep (10-minute grace period, one per call, claim by rename);
  - the reserved normalized path shared by `FileCache` and the cleaner via `CacheTrash`.

  Keep the existing `SafeRemover` / `FileCache::remove()` sentences.
- `docs/guides/tent/middlewares.md`:
  - Near the `FileCacheMiddleware` note "To clear the cache, delete the files in the `location` directory", mention the permanent `.tent-trash/` folder. It's safe to delete when it's empty, it's recreated on demand, and leftovers are swept automatically.
  - Note that a request path resolving under `/.tent-trash` is proxied but never cached.
  - In the `CacheCleanupMiddleware` section, add one sentence saying invalidation is atomic (rename then delete).

## Files to Change
- `docs/agents/architecture.md` — `CacheDirCleaner` paragraph.
- `docs/guides/tent/middlewares.md` — `FileCacheMiddleware` cache-location note and `CacheCleanupMiddleware` section.
