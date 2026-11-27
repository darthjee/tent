# Add CacheDirCleanerGuardTest

Add a new PHPUnit test class following the existing `CacheDirCleaner/*Test.php` conventions: a real temp-dir tree per test (`sys_get_temp_dir() . '/cache_dir_cleaner_guard_test_' . uniqid()`), `Logger::setInstance(new NullLoggerInstance())` in `setUp()`, restore `LoggerInstance` and remove the tree with `FileSystemUtils::removeDirRecursive()` in `tearDown()`, `createMock(LoggerInstance::class)` to assert log lines, and real `symlink()` fixtures called directly (as the existing symlink tests do — no skips).

Fixture: base `<tmp>/cache`, plus a sibling `<tmp>/outside` that must survive.

**Segment check**
- `cleanPath('/..')` with `<tmp>/GET` present → refused, directory survives, `warn` containing `cache cleanup refused` and `dot segment`.
- `clean('collection', '/../x')` → refused, nothing outside the base removed.
- In-cache `..`: `cleanPath('/games/../users.json')` with `cache/users.json/GET` present → refused and that directory **survives** (no retargeting).
- `.` segment: `cleanPath('/./users.json')` with `cache/users.json/GET` present → refused, survives.
- `...`, `..foo`, `foo..`, `%2e%2e` segments → accepted and deleted (one test per name, or a data provider).
- Path containing `"\0"` → refused with `warn`, no `ValueError` thrown.
- Base containing `..` (e.g. `FolderLocation('<tmp>/cache/sub/..')`) → a normal cleanup still deletes its target.
- Base with a trailing slash (`'<tmp>/cache/'`) → normal cleanup works.
- Base `/` → refused with `warn` containing `misconfigured cache location` (safe: the refusal happens before any filesystem access).

**`realpath` check**
- Intermediate symlink `cache/link` → `<tmp>/outside`, with `outside/x/GET` present, `cleanPath('/link/x')` → refused, `outside/x/GET` survives, `warn` containing `resolves outside cache location` and the real parent.
- Base is a symlink (`<tmp>/cachelink` → `<tmp>/realcache`) → deletion succeeds, no `warn`.
- Symlinked root inside a symlinked base (`realcache/users/GET` is a link to `outside`) → link removed, `outside` untouched, no `warn` (#316 preserved).
- Parent absent (`clean('collection', '/missing/1')`) → no-op, logger mock expects `never()`.
- Base absent (location pointing at a non-existent dir) → no-op, nothing logged.
- `cleanPath('/')` with `cache/GET` present → deleted.

## Files to Change
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerGuardTest.php` — new test class covering the cases above.
