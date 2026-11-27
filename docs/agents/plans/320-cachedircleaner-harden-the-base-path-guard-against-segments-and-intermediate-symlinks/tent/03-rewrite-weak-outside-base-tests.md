# Rewrite the two weak outside-base tests

`CacheDirCleanerCleanTest::testCleanDoesNotDeleteDirOutsideBaseLocation` and `CacheDirCleanerCleanPathTest::testCleanPathDoesNotDeleteDirOutsideBaseLocation` currently put the `..` in the **base** (`FolderLocation($this->cacheDir . '/users/../..')`), which passes the prefix check, and only pass because `<outside>/GET` never exists — they would keep passing with no guard at all. Since a base containing `..` is trusted operator configuration (still accepted after this change), they don't exercise the guard.

Rewrite both to:

- use the normal `$this->cleaner` (base `$this->cacheDir`);
- put the `..` in the path (e.g. `clean('collection', '/../' . basename($outsideDir) . '/x')` / `cleanPath('/../' . basename($outsideDir))`) so the resolved `$dir` climbs out of the base;
- create `<outside>/GET` (with a file inside) before cleaning, and assert it still exists afterwards.

Make sure the `..` count actually reaches the sibling `outsideDir` given `$this->cacheDir`'s location under `sys_get_temp_dir()`; keep the existing `try`/`finally` cleanup of `$outsideDir`.

## Files to Change
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanTest.php` — rewrite `testCleanDoesNotDeleteDirOutsideBaseLocation`.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanPathTest.php` — rewrite `testCleanPathDoesNotDeleteDirOutsideBaseLocation`.
