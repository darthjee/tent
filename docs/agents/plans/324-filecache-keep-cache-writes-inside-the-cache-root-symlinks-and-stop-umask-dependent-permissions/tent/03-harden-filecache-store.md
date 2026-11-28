# Harden FileCache::store()

In `FileCache` (write path only):

1. **Containment before `mkdir`**: in `store()`, after the existing `inert` check, find the deepest existing ancestor of `basePath()` (walk up with `dirname()` until `is_dir()`, stopping at the cache location) and require `$this->location->contains($ancestor)`. On failure, log the `warn` from the contract (`cache store skipped — path: <requestPath>, method: <METHOD>, reason: resolves outside cache location`) and return without creating or writing anything.
2. **`mkdir` mode**: `ensureCacheFolderExists()` uses `mkdir($basePath, 0755, true)`.
3. **Containment after `mkdir`**: require `$this->location->contains($basePath)`; same skip + `warn` on failure.
4. **Atomic writes**: write the body, then the meta, with `FileUtils::writeAtomically()` (body first, meta last — `exists()` requires both, so meta is the commit marker). If the body write fails, do not write the meta.

Update the class and `store()` docblocks: writes are contained and never follow symlinks; reads still trust the directory; TOCTOU limitation.

## Files to Change

- `source/source/lib/content/FileCache.php` — `store()`, `ensureCacheFolderExists()`, a private containment helper, docblocks.
- `source/tests/unit/lib/models/FileCache/FileCacheSymlinkTest.php` — new specs with a temp cache root and a sibling "outside" dir: intermediate directory symlinked outside → nothing written outside, `warn` logged, `exists()` false; symlink to a not-yet-existing outside path → no directory created outside; `<hash>.body.dat` / `.meta.json` pre-planted as symlinks to outside files → outside files untouched, entry holds regular files with the new content; cache root itself a symlink → store and read work; symlink to a directory inside the root → still cached.
- `source/tests/unit/lib/models/FileCache/FileCachePermissionsTest.php` — new: under `umask(0)`, created entry directories are `0755`, body/meta files `0644`; no temp files left behind.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/` — one middleware-level spec: with an intermediate symlink pointing outside, the request is still answered normally and nothing is written outside.
