# Rename-then-delete in CacheDirCleaner

Rework `CacheDirCleaner::deleteDir()` to rename the target into the trash before walking it.

Flow of `deleteDir(string $dir)`:
1. Run the existing scope check (`str_starts_with($dir, $base . '/')`), unchanged.
2. **New: reserved target.** If `$this->trash->contains($dir)`, return without doing anything. A collection, entity or custom target that resolves into the trash must never walk it.
3. Symlinked root: `is_link($dir)` → `removeFile($dir)`, as today (#316 rule, before any rename). A non-directory is ignored, as today.
4. **Rename path.** If `$this->trash->ensure()` returns true, build `$target = $this->trash->path() . '/' . $this->trash->newEntryName($this->now())` and call `$this->renameDir($dir, $target)`.
   - **Success**: log `cache cleared — dir: <original $dir>` at `debug` (meaning "this call invalidated it"), then run `removeDirRecursive($target)`. Its result no longer drives the debug line. Failures while deleting entries are still `warn`ed by `SafeRemover`.
   - **Failure**: `clearstatcache(true, $dir)`. If `!file_exists($dir) && !is_link($dir)`, the source is already gone because another cleaner won: return silently, with no log and no walk. Otherwise this is a real failure: `Logger::warn('cache trash rename failed — dir: <dir>, reason: <error_get_last() message>')`, then fall back.
5. **Trash unusable.** If `ensure()` returns false (the trash is a link, a regular file, or can't be created), log `warn` that the cache trash is not a real directory (including the trash path), then fall back.
6. **Fallback**: today's in-place `removeDirRecursive($dir)`. The debug line is logged only when the result is `RemovalResult::Removed`, as today.
7. Never throw.

New protected test-only seams, documented like `createIterator()`:
- `renameDir(string $from, string $to): bool`, wrapping `@rename($from, $to)`.
- `now(): int`, wrapping `time()`.

Construct a `CacheTrash` in the constructor, which keeps its signature `(FolderLocation, ?SafeRemover)`. Update the class docblock to describe rename-then-delete, the trash folder, the fallback, and the reserved-target no-op.

Tests:
- **Adapt `CacheDirCleanerRecursiveRemovalTest`.** Its `racingCleaner` callbacks now receive the trash entry as `$dir`. Disturb `$dir`, not `$this->targetDir`, and re-point the root-warning assertions at the trash entry where the root `rmdir` now happens. The symlinked-root specs must keep passing unchanged.
- New `CacheDirCleanerRenameTest`:
  - On the happy path, the target is gone, `.tent-trash/` exists and is empty, and there's exactly one `cache cleared — dir: <original>` line.
  - **Lost race**: `renameDir` deletes the source and returns `false`. Expect no log, and `createIterator` is never called.
  - **Concurrent writer**: `renameDir` renames, then re-creates `<base>/users/GET/<file>`. The fresh file survives, the trash entry is deleted, and there's no `warn`.
  - **Real failure**: `renameDir` returns `false` with the source present. Expect one `warn` with the reason, and the in-place deletion completes.
  - `.tent-trash` is a regular file → `warn` + fallback, and the file is untouched.
  - **Security**: `.tent-trash` is a symlink to a directory outside the cache → `warn` + fallback, and the target's contents are untouched.
  - `cleanPath('/.tent-trash/x')` and `cleanPath('/./.tent-trash/x')` do nothing, and planted trash entries are untouched.

## Files to Change
- `source/source/lib/content/CacheDirCleaner.php` — rename-then-delete flow, fallback, reserved-target no-op, seams, docblock.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerRecursiveRemovalTest.php` — adapt the race specs to the trash-entry `$dir`.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerRenameTest.php` — new.
