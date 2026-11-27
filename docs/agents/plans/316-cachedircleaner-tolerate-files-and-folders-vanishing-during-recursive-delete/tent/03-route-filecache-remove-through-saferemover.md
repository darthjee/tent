# Route FileCache::remove() through SafeRemover

Close the other side of the citadel#41 race: `FileCache::remove()` stops doing `FileUtils::exists()` + bare `unlink()`.

- Inside `remove()`, create `$remover = new SafeRemover();` (no constructor change — `FileCache`'s callers in `FileCacheMiddleware` and `CacheStalenessMiddleware`, and `BackgroundRefresher`'s `->remove()` calls, stay untouched).
- Replace both `if (FileUtils::exists(...)) { unlink(...); }` blocks with `$remover->removeFile($this->bodyFilePath);` and `$remover->removeFile($this->metaFilePath);`. Results are ignored; real failures are already logged by the helper.
- `$this->content = null;` still runs unconditionally.
- Add `use Tent\Utils\SafeRemover;`; drop the `FileUtils` import only if it becomes unused (Slevomat `UnusedUses` will flag it).
- Update the method PHPDoc to say vanished files are tolerated silently.

**Tests** — new `FileCacheRemoveTest` (follow `FileCacheStoreTest`'s setup for building a `FileCache` against a temp `FolderLocation`):

- `testRemoveDeletesBodyAndMetaFiles` — both present → both gone.
- `testRemoveIsSilentWhenFilesAlreadyGone` — neither present → no exception, no log (mock `LoggerInstance` expects no warn).
- `testRemoveIsSilentWhenOnlyOneFileExists` — only one present → it is removed, no warn for the missing one.

## Files to Change
- `source/source/lib/content/FileCache.php` — `remove()` delegates to `SafeRemover::removeFile()`; imports adjusted; PHPDoc.
- `source/tests/unit/lib/models/FileCache/FileCacheRemoveTest.php` — **new**: 3 specs.
