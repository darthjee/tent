# Path normalization and the CacheTrash helper

Introduce the single source of truth for the trash folder, so the cleaner and both guards share one name and one "is this path inside the trash?" rule.

1. **`FileUtils::normalizePath(string $path): string`** does purely lexical normalization with no filesystem access and no `realpath`. It collapses repeated `/`, drops `.` segments, and resolves `..` against the preceding segment. It keeps a leading `/` for absolute paths, and keeps a leading relative base such as `./cache` relative. A `..` that would climb above the first segment is kept as-is rather than silently dropped (the traversal itself is #322's concern). Example: `./cache/./.tent-trash//x/GET` → `cache/.tent-trash/x/GET`, consistently for both sides of a comparison.
2. **New `Tent\Content\CacheTrash`** (`source/source/lib/content/CacheTrash.php`), built from a `FolderLocation`:
   - `public const NAME = '.tent-trash';`
   - `path(): string` returns `<base>/.tent-trash` via `FileUtils::getFullPath()`.
   - `contains(string $path): bool` is true when `normalizePath($path)` equals `normalizePath(path())` or starts with it followed by `/`, compared case-insensitively (`strtolower` on both sides).
   - `ensure(): bool` creates the folder on demand with a non-recursive `@mkdir($path, 0777)`. Whether or not `mkdir` failed (for example because a concurrent `mkdir` won), it then `clearstatcache(true, $path)`s and returns `isUsable()`. It never deletes or repairs anything.
   - `isUsable(): bool` returns `is_dir($path) && !is_link($path)`.
   - `newEntryName(int $now): string` returns `$now . '.' . bin2hex(random_bytes(8))`.
   - `entryTimestamp(string $name): ?int` parses the `<ts>.` prefix, and returns `null` for names that don't match `/^\d+\.[0-9a-f]{16}$/`.
3. Register both in `source/source/loader.php`. `CacheTrash.php` goes before `FileCache.php` and `CacheDirCleaner.php`. `FileUtils` is already loaded early.

Tests:
- `FileUtils` normalization cases: `//`, `/./`, `..`, leading `./`, trailing `/`, and a `..` above the root kept as-is.
- `CacheTrash` cases:
  - `contains()` for the trash itself, entries beneath it, `/./.tent-trash/x`, `//.tent-trash`, `/.TENT-TRASH/x`, and `/users/.tent-trash` (false).
  - `ensure()` when the folder is missing, already present, a regular file, or a symlink to a directory (false in the last two cases, and the file or link is left untouched).
  - The `newEntryName()` pattern and `entryTimestamp()` parsing, including rejecting foreign names.

## Files to Change
- `source/source/lib/utils/FileUtils.php` — add `normalizePath()`.
- `source/source/lib/content/CacheTrash.php` — new helper class.
- `source/source/loader.php` — `require_once` for `CacheTrash.php`, before `FileCache.php` and `CacheDirCleaner.php`.
- `source/tests/unit/lib/utils/FileUtils/FileUtilsNormalizePathTest.php` — new.
- `source/tests/unit/lib/content/CacheTrash/CacheTrashTest.php` — new (split into several files if it grows, following the folder-per-class convention).
