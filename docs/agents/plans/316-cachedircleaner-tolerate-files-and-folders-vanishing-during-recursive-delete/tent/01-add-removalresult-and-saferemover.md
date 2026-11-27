# Add RemovalResult and SafeRemover

Create the tolerant-removal primitives in `lib/utils` and register them in the loader.

**`Tent\Utils\RemovalResult`** — a pure (non-backed) enum with three cases, each PHPDoc'd:

- `Removed` — this call removed the path.
- `AlreadyGone` — the operation failed but the path no longer exists (concurrent deletion).
- `Failed` — the operation failed and the path still exists; a `warn` has already been logged.

**`Tent\Utils\SafeRemover`** — instantiable class, two public methods:

```php
public function removeFile(string $path): RemovalResult;
public function removeDir(string $path): RemovalResult;
```

Both share one private shape (e.g. `private function attempt(callable $op, string $path, string $kind): RemovalResult`):

1. `error_clear_last()`, then call `@unlink($path)` / `@rmdir($path)` — the `@` wraps only that single call.
2. Success → `RemovalResult::Removed`.
3. On failure, immediately capture `error_get_last()['message'] ?? 'unknown error'`.
4. Existence re-check with `file_exists($path) || is_link($path)` (a dangling symlink must count as present). Not present → `RemovalResult::AlreadyGone`, nothing logged.
5. Still present → `Logger::warn('cache removal failed — ' . $kind . ': ' . $path . ' — ' . $error)` with `$kind` = `file` / `dir`, return `RemovalResult::Failed`.
6. No exception may escape.

Full PHPDoc on class and methods (the `Squiz.Commenting.FunctionComment` sniff applies to `source/`).

**Loader**: add `require_once __DIR__ . '/lib/utils/RemovalResult.php';` then `require_once __DIR__ . '/lib/utils/SafeRemover.php';` in the early utils group of `source/source/loader.php` (next to `FileUtils.php` / `PlaceholderPattern.php`), so both are loaded before `lib/content/CacheDirCleaner.php` and `lib/content/FileCache.php`.

**Tests** (PHPUnit, `Logger::setInstance(new NullLoggerInstance())` in `setUp()` unless a mock is asserted; temp dirs created and torn down per test):

`SafeRemoverRemoveFileTest`
- `testRemoveFileDeletesExistingFile` — file gone, returns `Removed`, no log.
- `testRemoveFileIsSilentWhenFileAlreadyGone` — nonexistent path returns `AlreadyGone`, no log.
- `testRemoveFileLogsWarningWhenDeletionFails` — called on a **directory** path (forces `@unlink` to fail while the path exists); mock `LoggerInstance` expects one warn whose message starts with `cache removal failed — file: ` and contains the path; returns `Failed`; no exception.

`SafeRemoverRemoveDirTest`
- `testRemoveDirDeletesEmptyDir` — returns `Removed`.
- `testRemoveDirIsSilentWhenDirAlreadyGone` — returns `AlreadyGone`, no log.
- `testRemoveDirLogsWarningWhenDirIsNotEmpty` — returns `Failed`, one warn starting `cache removal failed — dir: ` containing the path, directory still on disk.

Check how `LoggerInstance` routes `warn` (method name on the instance) before writing the mock expectation — mirror `StaticFileHandlerGeneralTest`'s existing mock usage.

## Files to Change
- `source/source/lib/utils/RemovalResult.php` — **new**: the enum.
- `source/source/lib/utils/SafeRemover.php` — **new**: the helper.
- `source/source/loader.php` — register both files in the early utils group.
- `source/tests/unit/lib/utils/SafeRemover/SafeRemoverRemoveFileTest.php` — **new**: 3 specs.
- `source/tests/unit/lib/utils/SafeRemover/SafeRemoverRemoveDirTest.php` — **new**: 3 specs.
