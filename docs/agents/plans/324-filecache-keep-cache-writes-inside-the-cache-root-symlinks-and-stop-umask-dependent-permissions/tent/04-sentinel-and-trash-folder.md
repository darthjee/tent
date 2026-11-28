# Sentinel and trash folder

- **Refresh sentinel**: `CacheStalenessMiddleware::writeSentinel()` uses `FileUtils::writeAtomically($sentinel, (string) time())` instead of `@file_put_contents()`, keeping best-effort semantics (result ignored). The sentinel lives in an existing entry directory, so no extra containment check is needed.
- **Trash folder**: `CacheTrash::ensure()` uses `@mkdir($path, 0755)` instead of `0777`.

## Files to Change

- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — `writeSentinel()`.
- `source/source/lib/content/CacheTrash.php` — `ensure()` mode.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/` — spec: sentinel path pre-planted as a symlink to an outside file → outside file untouched, sentinel is a regular `0644` file; existing debounce specs keep passing.
- `source/tests/unit/lib/content/CacheTrash/CacheTrashTest.php` — spec: under `umask(0)`, `ensure()` creates the trash folder as `0755`.
