# Extract FileUtils::hasSafeSegments()
Move `CacheDirCleaner::hasSafeSegments()` to a public static `FileUtils::hasSafeSegments(string $path): bool`, next to `normalizePath()`, keeping its semantics and docblock (whole-segment match on `.` and `..`, NUL byte rejected, no filesystem access). Make `CacheDirCleaner::isSafelyScoped()` call the new method and delete the private one. Behaviour of the cleaner is unchanged.

Tests (new `FileUtilsHasSafeSegmentsTest.php`):
- Rejected: `/a/../b`, `/../x`, `/a/..`, `/a/.`, `./a`, `a/../b`, `..`, `.`, a path with `"\0"`.
- Accepted: `/a/b`, `/...`, `/..foo`, `/a..b`, `/.git`, `/%2e%2e`, `/a\b`, `/a//b`, `''`, `/`.
- The existing `CacheDirCleanerGuardTest.php` specs keep passing unchanged.

## Files to Change
- `source/source/lib/utils/FileUtils.php` — add `hasSafeSegments()`.
- `source/source/lib/content/CacheDirCleaner.php` — use `FileUtils::hasSafeSegments()`; remove the private method.
- `source/tests/unit/lib/utils/FileUtils/FileUtilsHasSafeSegmentsTest.php` — new.
