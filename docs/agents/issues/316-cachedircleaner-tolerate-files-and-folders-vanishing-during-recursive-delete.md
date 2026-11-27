# Issue: CacheDirCleaner: tolerate files and folders vanishing during recursive delete

## Description

`CacheDirCleaner` resolves `collection` / `entity` / explicit-path targets to a directory under the file-cache location and deletes it. It is used by `CacheCleanupMiddleware` to drop stale `GET/` cache directories after a mutating request.

Deletion today is a straight recursive walk: `deleteDir()` checks the base-path prefix and `is_dir()`, then `removeDirRecursive()` iterates the tree with a `RecursiveDirectoryIterator` wrapped in a `RecursiveIteratorIterator` (CHILD_FIRST), calling `unlink()` / `rmdir()` per entry and finally `rmdir($dir)` — with no existence check, no error suppression and no `try/catch`.

That was fine while the cleaner was the only thing deleting cache entries. darthjee/citadel#41 changed that: four writers can now delete overlapping cache directories concurrently — a response-side invalidation middleware, a probabilistic GC middleware, `FileCache::remove()` and `CacheCleanupMiddleware`.

### Current behaviour (reference)

| Location | Behaviour today |
| --- | --- |
| `CacheDirCleaner::removeDirRecursive()` (https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/content/CacheDirCleaner.php) | Iterates with `RecursiveIteratorIterator(..., CHILD_FIRST)` and calls `unlink()` / `rmdir()` per entry, then `rmdir($dir)`. No existence check, no error suppression, no try/catch. |
| `CacheDirCleaner::deleteDir()` (same file) | Guards with `str_starts_with($dir, $base . '/')`, then `is_dir($dir)`, then delegates to `removeDirRecursive()`; emits `Logger::debug('cache cleared — dir: ' . $dir)` after a successful removal. The `is_dir()` check is racy (TOCTOU). |
| `CacheDirCleaner::clean()` (same file) | Resolves via `CacheDirResolver::resolve()` and calls `deleteDir()` only when the result is non-null. |
| `CacheDirCleaner::cleanPath()` (same file) | Resolves via `CacheDirResolver::resolveExact()` and calls `deleteDir()` unconditionally. |
| `CacheDirResolver::resolve()` (https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/content/CacheDirResolver.php) | Returns `null` for unknown targets and for `entity` on single-segment paths; `resolveExact()` always returns a path. |
| `FileCache::remove()` (https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/content/FileCache.php) | `FileUtils::exists()` then bare `unlink()`, separately for the body and meta files — the same check-then-delete race. |
| `CacheStalenessMiddleware::removeSentinel()` (https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | `file_exists()` then `@unlink()`; deliberately ignores failures. Already warning-free. |
| `FileUtils` (https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/utils/FileUtils.php) | Provides `getFullPath()` and `exists()` (file-only, `file_exists() && is_file()`). No removal helper exists. |
| `CacheDirCleanerCleanTest` (https://raw.githubusercontent.com/darthjee/tent/main/source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanTest.php) | 11 specs: collection/entity resolution, no-op on missing dir, unknown target, unrelated dirs, out-of-base refusal, debug log on delete, no log on miss. |
| `CacheDirCleanerCleanPathTest` (https://raw.githubusercontent.com/darthjee/tent/main/source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerCleanPathTest.php) | 6 specs: same surface through `cleanPath()`. |

### Context

- Issue: darthjee/tent#316 (https://github.com/darthjee/tent/issues/316).
- Origin: darthjee/citadel#41, where four concurrent writers can delete the same cache directories.
- Follow-up: darthjee/tent#319 — atomic rename-then-delete.
- Follow-up: darthjee/tent#320 — harden the base-path guard against `..` segments and intermediate symlinks.
- Affected module: `source/source/lib/content/` (`CacheDirCleaner`, `FileCache::remove()`), `source/source/lib/utils/` (new `SafeRemover`, `RemovalResult`), `source/source/loader.php`.
- Reference doc: docs/agents/architecture.md (https://raw.githubusercontent.com/darthjee/tent/main/docs/agents/architecture.md) — content abstractions, `lib/utils` role, class-loading rule, testing conventions.

## Problem

Under concurrent deletion the cleaner reports spurious failures:

- Any node that disappears between an iteration step and its `unlink()` / `rmdir()` raises a PHP warning; the final `rmdir($dir)` can fail the same way.
- The iterator itself throws `UnexpectedValueException` when a subdirectory disappears mid-descent — and its constructor throws when the root is already gone.
- `deleteDir()`'s `is_dir()`-then-delete is a classic TOCTOU window.
- `FileCache::remove()` has the same `exists()`-then-`unlink()` race on the other side.

Depending on the application's error handler, these either pollute the log or turn a successful write request into a 5xx.

The semantics we want are simple: *already gone* is success, *cannot be removed* is a logged warning. Today the code distinguishes neither.

Separately, reading the code surfaced a latent safety gap: if the resolved root is itself a symlink to a directory, `RecursiveDirectoryIterator` follows it, so the cleaner can delete content outside the cache base — the prefix guard checks the link's path, not its target.

## Expected Behavior

- A directory that no longer exists yields a silent no-op through `clean()` and `cleanPath()`.
- A file removed between iteration and `unlink()` produces no warning, no exception, and no log entry.
- A walk interrupted by a vanished subdirectory is restarted (bounded to 3 attempts), so contention never leaves a half-deleted tree or a spurious "not empty" warning.
- A real failure (e.g. permission denied) is logged at `warn` with the path and is never re-thrown.
- Every resolved path is still checked by the existing base-path prefix guard before any filesystem call (its known gaps are tracked in darthjee/tent#320).
- Symlinks inside the tree are unlinked; their targets are untouched.
- A resolved root that is a symlink is unlinked without being followed; a resolved root that is a regular file is left alone silently.
- `FileCache::remove()` on a body or meta file that a concurrent cleaner already deleted produces no warning and no log.
- No behaviour change for `clean()` / `cleanPath()` on an uncontended tree, including the existing `cache cleared — dir: …` debug line.
- The public API of `CacheDirCleaner` stays source-compatible: `new CacheDirCleaner($location)` keeps working unchanged.

## Solution

A new helper in `lib/utils`, `Tent\Utils\SafeRemover`, owns the tolerance logic. `CacheDirCleaner` keeps its public surface and receives the helper through an optional constructor argument; `FileCache::remove()` uses it too. Deletion stays in place (no rename, no locking) with a bounded restart of the walk when contention interrupts it.

### Scope

**In scope**

- `CacheDirCleaner` (`clean()`, `cleanPath()`, `deleteDir()`, `removeDirRecursive()`) — the recursive delete becomes tolerant of concurrent removal.
- New `Tent\Utils\SafeRemover` helper and `Tent\Utils\RemovalResult` enum in `lib/utils`, plus their `loader.php` registration.
- `FileCache::remove()` — one of the four concurrent writers named in darthjee/citadel#41; fixing only the cleaner would leave the other side of the race emitting warnings. Both `unlink()` calls are delegated to `SafeRemover::removeFile()` and the `exists()` guards are dropped.

**Out of scope**

- `CacheStalenessMiddleware::removeSentinel()` — already warning-free and intentionally ignores failures; routing it through `SafeRemover` would only add unwanted `warn` logs.
- `CacheDirResolver`, `Cache`, and every middleware — no behaviour change.
- Any change to cache layout, eviction policy, or locking — this issue only makes deletion tolerant; it does not serialise the concurrent writers.
- Atomic rename-then-delete — deferred to darthjee/tent#319.
- Hardening the base-path guard against `..` segments and intermediate symlinks — deferred to darthjee/tent#320.

### API

```php
namespace Tent\Utils;

enum RemovalResult
{
    case Removed;      // this call removed the path
    case AlreadyGone;  // the path did not exist after the failed operation (concurrent deletion)
    case Failed;       // the path still exists; a warn has already been logged
}

class SafeRemover
{
    public function removeFile(string $path): RemovalResult;

    public function removeDir(string $path): RemovalResult;
}
```

`RemovalResult` is a pure (non-backed) enum — the first enum in the codebase; the runtime is PHP 8.4.

```php
namespace Tent\Content;

class CacheDirCleaner
{
    public function __construct(FolderLocation $location, ?SafeRemover $remover = null);
}
```

### Semantics

1. `removeFile($path)` calls `@unlink($path)`. If it returns `true`, the result is `RemovalResult::Removed`.
2. If it returns `false`, the helper re-checks existence. When the path no longer exists, the failure is attributed to a concurrent deletion: `RemovalResult::AlreadyGone`, nothing logged.
3. If the path still exists after the failed `unlink()`, the removal failed for a real reason: **`SafeRemover` itself** logs `Logger::warn('cache removal failed — file: <path> — <error>')` and returns `RemovalResult::Failed`. `<error>` is `error_get_last()['message']`, read immediately after the suppressed call (before the existence re-check), falling back to `unknown error` when it is `null`.
4. `removeDir($path)` follows the same shape with `@rmdir($path)`: success → `Removed`; failure plus nonexistence → `AlreadyGone`; failure plus still-present → `Failed`, logged by the helper as `cache removal failed — dir: <path> — <error>`.
5. Callers (`CacheDirCleaner`, `FileCache::remove()`) never log removal failures themselves; they only act on the result. `FileCache::remove()` ignores it entirely.
6. The `@` operator is used *only* around the single FS call, never around the existence re-check, so the diagnostic path stays clean.
7. No exception ever escapes `SafeRemover`.

### State detection

"Already deleted" is defined strictly as *the path does not exist after the failed operation*. Nothing else counts. A path that exists but is not removable is a real failure; a path that exists and is a directory when a file was expected is also a real failure (the `@unlink()` fails, existence re-check succeeds).

### Order of checks in `deleteDir()`

`deleteDir()` drops the `is_dir()` *existence* precondition — absence is never pre-checked, it is decided by the removal itself. The checks run in this order:

1. **Prefix guard** (unchanged): anything outside `$location->basePath() . '/'` returns before any filesystem call.
2. **Root is a symlink** (`is_link($dir)`): the link itself is removed with `SafeRemover::removeFile()` and the cleaner returns without descending. The link's target is never touched, so the prefix guard is a real boundary even when the resolved path is a link pointing outside the cache base.
3. **Root exists but is not a directory** (`file_exists($dir) && !is_dir($dir)`, e.g. a regular file): silent no-op, nothing deleted, nothing logged — the same as today. The cleaner only ever removes directories (and links standing in their place). This type check is safe from races: if the path vanishes between the check and the return, the outcome is the same.
4. Otherwise, the recursive removal below runs. A root that is already absent surfaces as the iterator constructor's `UnexpectedValueException` and is treated as success.
5. **Debug log rule**: `Logger::debug('cache cleared — dir: ' . $dir)` is emitted **only when this call's own root removal returned `RemovalResult::Removed`**. `AlreadyGone` (root missing up front, or removed by a concurrent writer mid-walk — even if this call deleted part of the tree) and `Failed` emit no debug line. A symlinked root that is unlinked (step 2) follows the same rule on `removeFile()`'s result. When the root removal reports a real failure (e.g. the directory is non-empty because a concurrent writer re-created content), `SafeRemover` logs the warning, the cleaner abandons the removal and returns normally — `clean()` / `cleanPath()` keep their `void` contract and never throw.

### Recursive removal

`removeDirRecursive()` becomes a loop over the iterator that delegates every `unlink` and every intermediate `rmdir` to the helper. Both the iterator's **construction** and its traversal sit inside a `try/catch` for `UnexpectedValueException`: `new RecursiveDirectoryIterator($dir)` throws when `$dir` is already gone, and the traversal throws when a subdirectory disappears mid-descent.

When the exception is caught:

1. If the root no longer exists, the walk is finished — the race removed it for us. Silent.
2. If the root still exists, the walk is **restarted from the root**, up to **3 attempts in total**. A fresh walk picks up the siblings the interrupted walk never reached, so contention ends in a fully removed tree instead of a "directory not empty" failure.
3. After the last attempt (or after any walk that completes without an exception), the root removal is delegated to `SafeRemover::removeDir()`, whose `RemovalResult` drives the debug log rule above. A real failure — e.g. an unreadable subdirectory, which throws on every attempt — therefore surfaces as a single `warn` for the root, not one per attempt.

The attempt limit is a private constant on `CacheDirCleaner`; it is not configurable.

The iterator is built through a **protected factory method**, `protected function createIterator(string $dir): \Iterator`, which by default returns the `RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), CHILD_FIRST)` used today. It exists solely as a test seam (see Testing) and is not part of the public API; it is called once per attempt, inside the `try`.

Removal order stays CHILD_FIRST: files and links first, then directories, then the root directory. Symlinks inside the tree are removed by `unlink()`, never followed.

### Alternatives considered

| Alternative | Verdict | Why |
| --- | --- | --- |
| Temporary `set_error_handler()` that swallows warnings during the delete | Rejected | Mutates global state, can clash with the application's or PHPUnit's own handler, and decides success from warning text rather than filesystem state. |
| Locking (`flock` on a per-tree lock file) to serialise the concurrent writers | Rejected | Lock-file management, added latency on every write request, deadlock risk; serialising writers is explicitly out of scope. |
| Shelling out to `rm -rf` | Rejected | Process spawn per call, not portable, poor error reporting. |
| `symfony/filesystem` `Filesystem::remove()` | Rejected | Adds a runtime dependency (today only `monolog/monolog`) and its `IOException`s would still need wrapping to meet the no-throw contract. |
| Atomic rename-then-delete (rename the root to `<dir>.deleting.<uniqid>`, then delete the private tree) | Deferred to darthjee/tent#319 | Strongest guarantees (one cleaner wins, writers can't refill the tree, atomic invalidation for readers), but introduces orphan `*.deleting.*` handling. #316 stays small with in-place deletion + bounded restart; #319 can layer on top. |

### Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Where the tolerance lives | New `Tent\Utils\SafeRemover` in `lib/utils` | Matches the existing utils role; keeps `CacheDirCleaner` focused on resolution + orchestration. |
| Tolerance mechanism | Suppress the warning with `@`, then re-check existence after the failure | No global error-handler juggling; the failure/success distinction is decided by the filesystem state, not by the warning text. |
| Real-failure reporting | `Logger::warn()` with path and error; no log for already-deleted | Distinguishes contention (expected, quiet) from genuine failure (actionable). |
| Result type | `Tent\Utils\RemovalResult` enum: `Removed` / `AlreadyGone` / `Failed` | A `bool` cannot tell "removed" from "already gone", yet the debug line must only fire on an actual removal; an enum makes all three outcomes explicit at call sites. |
| Debug line when a concurrent writer took the root mid-walk | Not emitted | Simple, testable rule: log iff this call's own root removal returned `Removed`. |
| Who emits the `warn` | `SafeRemover`, never the caller | `error_get_last()` must be read right after the suppressed call, which only the helper can do reliably; every caller (including `FileCache::remove()`) gets consistent logging for free. |
| Warning format | `cache removal failed — file: <path> — <error>` / `cache removal failed — dir: <path> — <error>` | Mirrors the existing `cache cleared — dir: …` debug line so cache log lines read uniformly. |
| Failure containment | No exceptions escape; `clean()` / `cleanPath()` stay `void` | A write request must never become a 5xx because cache GC raced with it. |
| Helper shape | Instantiable class with instance methods, injected via optional constructor argument | A static-only API has no seam a test can double; instance methods keep `CacheDirCleaner` testable for the mid-walk race while the default keeps call sites untouched. |
| TOCTOU in `deleteDir()` | Remove the `is_dir()` existence precondition; let the removal decide | Existence is decided after the operation, which closes the race. |
| Mid-walk `UnexpectedValueException` | Restart the walk from the root, at most 3 attempts in total, then attempt the root removal | Stopping after the first exception leaves unreached siblings behind and turns pure contention into a "not empty" warning; a bounded restart cleans fully while a persistent failure still warns once. |
| Resolved path is a regular file | Silent no-op | Preserves today's behaviour; the cleaner removes directories only. |
| Resolved root is a symlink | Unlink the link, never descend | `RecursiveDirectoryIterator` follows a symlinked root, so today's code can delete content outside the cache base. |
| `FileCache::remove()` | In scope: both `unlink()` calls go through `SafeRemover::removeFile()`; the `FileUtils::exists()` guards are removed | Same TOCTOU race, and it is one of the concurrent writers in darthjee/citadel#41 — fixing only the cleaner leaves half the race. |
| `CacheStalenessMiddleware::removeSentinel()` | Out of scope, unchanged | Already warning-free (`file_exists()` + `@unlink()`) and deliberately ignores failures; `SafeRemover` would add `warn` noise. |
| Test seam for the mid-walk race | `protected createIterator(string $dir): \Iterator` on `CacheDirCleaner`, overridden by an anonymous subclass in tests | Whether a sibling deleted during a real walk makes SPL throw or silently skip depends on OS/filesystem readdir buffering, so FS-level races are flaky; the seam makes the exception deterministic without touching the public constructor. |
| Iterator | `RecursiveDirectoryIterator` / `RecursiveIteratorIterator` (standard PHP SPL) | Already the implementation in use; no new iteration primitive introduced. |

### Edge cases (pinned)

- Target directory already absent when `deleteDir()` runs → silent no-op, no debug log.
- Target directory vanishes between the prefix guard and the iterator's construction → constructor's `UnexpectedValueException` caught, root no longer exists, silent no-op.
- Empty directory that exists → removed, debug log emitted.
- File vanishes between iteration and removal → tolerated silently, remaining entries still processed.
- Subdirectory vanishes mid-walk → `UnexpectedValueException` caught, root still exists, walk restarted from the root (up to 3 attempts in total); siblings the first walk never reached are removed on the restart and no warning is logged.
- Subdirectory is unreadable (e.g. permission denied) → the walk throws on every attempt; after the 3rd attempt the root `rmdir` fails, and exactly one `warn` is logged for the root.
- Root `rmdir` fails because a concurrent writer re-created content → warn logged, removal abandoned, no exception.
- Resolved path exists but is a regular file → silent no-op, file untouched, no log (unchanged from today).
- Resolved root is itself a symlink to a directory → the link is unlinked; the target directory and its contents are untouched, even when the target lies outside the cache base.
- Symlink inside the tree pointing outside the base location → the link is unlinked; the target is untouched.
- Unknown target (`clean()` with a target other than `collection` / `entity`) → unchanged: resolver returns `null`, nothing is deleted.
- `entity` target on a single-segment path → unchanged: resolver returns `null`, nothing is deleted.
- Removal attempt on a path outside the base location → refused by the existing prefix guard, before any filesystem call.

### Backward compatibility

**Source compatibility — unchanged**

- `new CacheDirCleaner($location)` keeps working: the new `?SafeRemover $remover = null` parameter is optional and defaults to a real `SafeRemover`. The only production call site, `CacheCleanupMiddleware`, is untouched.
- `clean()` / `cleanPath()` keep their signatures and `void` return; `deleteDir()` / `removeDirRecursive()` are private, and no class extends `CacheDirCleaner`.
- `FileCache`'s constructor and `remove(): void` signature are unchanged; its callers (`FileCacheMiddleware` ×2, `BackgroundRefresher` ×2) need no change. `remove()` still nulls `$this->content`.
- `SafeRemover` and `RemovalResult` are purely additive.
- No developer-facing docs (`docs/`, README) mention `CacheDirCleaner` or `FileCache::remove()`, so no extension-doc update is needed.

**Behaviour on an uncontended tree — unchanged**

- Same directories deleted, same `cache cleared — dir: …` debug line, still no log when the directory did not exist. The existing 11 + 6 `clean()` / `cleanPath()` specs pass unmodified.

**Intentional behaviour changes**

| Situation | Before | After |
| --- | --- | --- |
| Entry or root vanishes during the delete | PHP warning (or an exception / 5xx under a strict error handler) | Silent; treated as success |
| Real removal failure (e.g. permission denied) | PHP warning from `unlink()` / `rmdir()` | No PHP warning; one `Logger::warn('cache removal failed — file\|dir: <path> — <error>')` per failed path |
| Subdirectory vanishes mid-walk | `UnexpectedValueException` escapes `clean()` / `cleanPath()` | Caught; walk restarted (≤ 3 attempts in total) |
| Resolved root is a symlink to a directory | Link followed: the target's contents are deleted (possibly outside the cache base), then `rmdir()` on the link fails with a warning | The link alone is unlinked; the target is untouched |
| `FileCache::remove()` races a concurrent delete | PHP warning from `unlink()` | Silent |

Anyone alerting on PHP warnings from cache cleanup will stop seeing them; real failures now appear as `warn` log lines with the `cache removal failed` prefix instead. The symlinked-root change is a safety fix and is not expected to be relied upon.

### Performance & security

**Performance**

- Uncontended deletes issue the same one syscall per entry as today; the existence re-check and `error_get_last()` run only after a *failed* call.
- `deleteDir()` performs 2–3 `stat` calls on the root (`is_link`, `file_exists`, `is_dir`) instead of one `is_dir` — negligible.
- The walk restart is bounded to 3 attempts and only triggers on contention or a persistent failure. Cleanup runs inline in the write request, so the worst case is three partial walks — never an unbounded loop.

**Security**

- Improved here: a symlinked resolved root is unlinked instead of followed, so the cleaner can no longer delete content outside the cache base through it; symlinks inside the tree are unlinked, never followed.
- Unchanged here, tracked in darthjee/tent#320: the base-path prefix guard is a string check on an un-normalised path, so `..` segments (e.g. `cleanPath('/../..')` → `./cache/../../GET`) pass it, and a symlink in an *intermediate* path component passes both the guard and the root `is_link()` check. #316 keeps the guard exactly as-is; hardening it is #320's job.
- Log content: the `warn` message includes the resolved path, which derives from the request path or a configured target — the same data already written by the existing `cache cleared — dir: …` debug line, so no new exposure.

### Implementation notes

- `source/source/lib/utils/SafeRemover.php` and `source/source/lib/utils/RemovalResult.php` are **new** files. `source/source/loader.php` must gain a `require_once` for each (`RemovalResult` before `SafeRemover`) — there is no PSR-4 autoload at runtime, and the utils group is where it belongs (see docs/agents/architecture.md, section "Class Loading (loader.php)").
- No change to `CacheDirResolver`, `Cache`, or any middleware.
- `FileCache::remove()` delegates both `unlink()` calls to a `SafeRemover` instance created internally (`new SafeRemover()`); the `FileCache` constructor signature is unchanged, so its call sites in `FileCacheMiddleware` and `CacheStalenessMiddleware` are untouched. `$this->content = null` still runs unconditionally.
- `CacheDirCleaner`'s constructor keeps the `FolderLocation` first parameter; the optional second parameter must default to a `SafeRemover` instance so existing call sites (including `CacheCleanupMiddleware`) compile and behave identically.
- The base-path prefix guard stays exactly as-is and must run before any filesystem call, including the re-checks inside the helper.
- Keep the `Logger::debug('cache cleared — dir: ' . $dir)` line and the "no log when the directory did not exist" behaviour intact — both are asserted by existing specs.
- PSR-12 applies; `composer lint` / `composer lint:fix` gate the change.
- The `@` operator is lint-clean here: `source/phpcs.xml` is PSR-12 plus a few Slevomat sniffs (unused variables/uses) and enables no silence-operator rule, and `@unlink()` already passes `composer lint` in `CacheStalenessMiddleware::removeSentinel()`. No `phpcs:ignore` is needed.

### Testing

All specs are PHPUnit, under `source/tests/`, following the existing per-class folder convention (`source/tests/unit/lib/content/CacheDirCleaner/<Class><Method>Test.php`). Logger is replaced with `NullLoggerInstance` in `setUp()` except where a mock is asserted.

**Determinism rules**

- **Never rely on a real filesystem race inside the SPL walk.** Whether the iterator throws or skips a sibling deleted mid-walk depends on readdir buffering. Specs that need `UnexpectedValueException` use an anonymous subclass of `CacheDirCleaner` overriding `createIterator()` to throw on demand (first attempt only, every attempt, or after removing the root), while the actual removals stay real.
- **Never rely on `chmod` / permissions to force a real failure.** `removeFile()` → `Failed` is forced by calling it on a directory path (`@unlink` fails, path still exists); `removeDir()` → `Failed` by a non-empty directory. Tests run as non-root (`app` locally, the `cimg/php` user in CI), but permission tricks remain avoided for portability.
- Races on a **specific path** (spec 2) are fine via a `SafeRemover` double: it deletes the exact path just before delegating, which is deterministic.

`source/tests/unit/lib/utils/SafeRemover/SafeRemoverRemoveFileTest.php` (proposed path)
- `testRemoveFileDeletesExistingFile` — file gone, returns `RemovalResult::Removed`, no log.
- `testRemoveFileIsSilentWhenFileAlreadyGone` — nonexistent path returns `RemovalResult::AlreadyGone`, no log, no warning.
- `testRemoveFileLogsWarningWhenDeletionFails` — called on a directory path (forces `@unlink` to fail while the path still exists); with a mock `LoggerInstance`, `Logger::warn()` is called once with a message starting `cache removal failed — file: ` and containing the path; returns `RemovalResult::Failed`; no exception.

`source/tests/unit/lib/utils/SafeRemover/SafeRemoverRemoveDirTest.php` (proposed path)
- `testRemoveDirDeletesEmptyDir` — returns `RemovalResult::Removed`.
- `testRemoveDirIsSilentWhenDirAlreadyGone` — returns `RemovalResult::AlreadyGone`, no log.
- `testRemoveDirLogsWarningWhenDirIsNotEmpty` — non-empty directory: returns `RemovalResult::Failed`, `Logger::warn()` called once with a message starting `cache removal failed — dir: ` and containing the path, directory still on disk.

`source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerRecursiveRemovalTest.php` (proposed path) — the scenarios named in the issue plus the pinned edge cases:

1. `testDeletingDirThatNoLongerExistsIsSilentNoOp` — `clean()` and `cleanPath()` on a missing directory: no exception, no log, no warning.
2. `testToleratesFileRemovedBetweenIterationAndUnlink` — a `SafeRemover` double passed through the constructor removes the target before delegating to the real removal, simulating the race; assertion: no exception, no warning, sibling entries removed.
3. `testToleratesSubdirectoryRemovedMidWalk` — a `createIterator()` override deletes a subdirectory and throws `UnexpectedValueException` on the first attempt only; assertion: no exception, no warning, the tree is gone.
4. `testSymlinkInsideTreeIsRemovedAndTargetUntouched` — a symlink pointing to a file outside the cache root: the link is gone, the target still exists.
5. `testRefusesPathOutsideCacheLocation` — mirroring the existing out-of-base specs: the outside directory survives.
6. `testRestartsWalkAndRemovesUnreachedSiblingsAfterMidWalkRace` — a `createIterator()` override throws on the first attempt while siblings remain unvisited, then returns the real iterator; assertion: `createIterator()` called twice, the whole tree including the root is gone, no warning logged.
7. `testLogsSingleWarningWhenWalkFailsOnEveryAttempt` — a `createIterator()` override throws on every attempt while the tree stays on disk; assertion: `createIterator()` called exactly 3 times, no exception escapes, `Logger::warn()` called exactly once (the root), root still on disk, no debug line.
8. `testResolvedPathThatIsAFileIsLeftUntouched` — a regular file at the resolved path: file still exists, no log.
9. `testSymlinkedRootIsUnlinkedAndTargetUntouched` — the resolved root is a symlink to a directory outside the cache base: the link is gone, the target directory and its contents survive.
10. `testNoDebugLogWhenRootRemovedByConcurrentWriterMidWalk` — a `createIterator()` override removes the whole tree (root included) and throws; assertion: no exception, no warning, no `cache cleared` debug line.

11. `testRealFileFailureLeavesRootAndLogsOnlyHelperWarnings` — a `SafeRemover` double returns `RemovalResult::Failed` for one file without deleting it (and without logging), delegating everything else to the real helper; assertion: no exception, no walk restart, exactly one `warn` (from the real root `removeDir()`, which fails because the directory is non-empty), no debug line, the file and root still on disk. Pins that a non-throwing real failure does not trigger a restart and that `CacheDirCleaner` itself never logs failures. In production the same situation yields two warns (file + root), both from `SafeRemover`.

`source/tests/unit/lib/models/FileCache/FileCacheRemoveTest.php` (proposed path)
- `testRemoveDeletesBodyAndMetaFiles` — both files present: both gone afterwards.
- `testRemoveIsSilentWhenFilesAlreadyGone` — neither file present: no exception, no warning, no log.
- `testRemoveIsSilentWhenOnlyOneFileExists` — only the meta (or body) file present: the present one is removed, no warning for the missing one.

No other specs are added; the 11 + 6 existing `clean()` / `cleanPath()` specs must keep passing unchanged.

## Benefits

- Concurrent cache writers (darthjee/citadel#41) no longer produce spurious PHP warnings or 5xx responses during cache cleanup.
- Clear separation between contention (silent, expected) and genuine removal failures (a single, greppable `cache removal failed — …` warn line).
- Closes a latent safety gap: a symlinked cache root is unlinked instead of followed outside the cache base.
- A reusable, test-seamed `SafeRemover` / `RemovalResult` pair in `lib/utils` for any future tolerant-removal needs.
- Fully deterministic test coverage of the race paths, with no reliance on filesystem timing or permissions.
