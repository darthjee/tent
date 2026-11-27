# Make CacheDirCleaner tolerant

Refactor `CacheDirCleaner` to delegate every removal to `SafeRemover`, close the TOCTOU, restart interrupted walks, and handle symlinked / non-directory roots. Public surface stays source-compatible.

**Constructor**

```php
public function __construct(FolderLocation $location, ?SafeRemover $remover = null)
```

Store `$remover ?? new SafeRemover()`. `CacheCleanupMiddleware`'s `new CacheDirCleaner($location)` is untouched.

**`deleteDir(string $dir)`** — checks in this exact order:

1. Prefix guard — unchanged, byte-for-byte; returns before any filesystem call.
2. `is_link($dir)` → `$result = $this->remover->removeFile($dir)`; if `Removed`, emit the debug line; return. Never descend.
3. `file_exists($dir) && !is_dir($dir)` → silent return (no deletion, no log).
4. Otherwise `$result = $this->removeDirRecursive($dir)`; emit `Logger::debug('cache cleared — dir: ' . $dir)` **only if** `$result === RemovalResult::Removed`.

The old `is_dir()` existence precondition is removed.

**`removeDirRecursive(string $dir): RemovalResult`**

- `private const MAX_WALK_ATTEMPTS = 3;`
- Loop up to `MAX_WALK_ATTEMPTS` times:
  - `try { foreach ($this->createIterator($dir) as $item) { ... } break; }` — construction is **inside** the `try`.
  - Per item: `isLink() || isFile()` → `$this->remover->removeFile($item->getPathname())`; `isDir()` → `$this->remover->removeDir(...)`. Results are not acted on (the helper already logged real failures).
  - `catch (\UnexpectedValueException)`: if `!file_exists($dir) && !is_link($dir)` → `return RemovalResult::AlreadyGone`; otherwise continue to the next attempt.
- After the loop (completed walk or attempts exhausted): `return $this->remover->removeDir($dir);`.

A non-throwing real failure (e.g. a file `removeFile()` returned `Failed` for) does **not** trigger a restart.

**`protected function createIterator(string $dir): \Iterator`** — returns `new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST)`. PHPDoc it as a test seam, not public API. Called once per attempt.

Update the class and method PHPDoc to describe the tolerance rules.

**Tests** — new `CacheDirCleanerRecursiveRemovalTest` (11 specs, per the issue's Testing section). Race specs use an anonymous subclass of `CacheDirCleaner` overriding `createIterator()` (counting calls, throwing on demand); removal stays real.

1. `testDeletingDirThatNoLongerExistsIsSilentNoOp` — `clean()` + `cleanPath()` on a missing dir: no exception, no log.
2. `testToleratesFileRemovedBetweenIterationAndUnlink` — `SafeRemover` double (subclass) deletes the exact path then delegates to `parent::removeFile()`; no warn, siblings removed.
3. `testToleratesSubdirectoryRemovedMidWalk` — override deletes a subdir and throws on attempt 1 only; tree gone, no warn.
4. `testSymlinkInsideTreeIsRemovedAndTargetUntouched`.
5. `testRefusesPathOutsideCacheLocation`.
6. `testRestartsWalkAndRemovesUnreachedSiblingsAfterMidWalkRace` — throws on attempt 1, real iterator after; `createIterator()` called twice; whole tree gone; no warn.
7. `testLogsSingleWarningWhenWalkFailsOnEveryAttempt` — throws every attempt, tree left intact; `createIterator()` called exactly 3 times; exactly one warn (root); root on disk; no debug line.
8. `testResolvedPathThatIsAFileIsLeftUntouched`.
9. `testSymlinkedRootIsUnlinkedAndTargetUntouched` — target dir outside the cache base survives with its contents.
10. `testNoDebugLogWhenRootRemovedByConcurrentWriterMidWalk` — override removes the whole tree and throws; no warn, no debug line.
11. `testRealFileFailureLeavesRootAndLogsOnlyHelperWarnings` — `SafeRemover` double returns `Failed` for one file without deleting or logging; no restart (`createIterator()` called once); exactly one warn (real root `removeDir()`); no debug line; file and root on disk.

Existing `CacheDirCleanerCleanTest` (11) and `CacheDirCleanerCleanPathTest` (6) must pass without modification.

## Files to Change
- `source/source/lib/content/CacheDirCleaner.php` — optional `SafeRemover` constructor arg, new `deleteDir()` check order and debug rule, bounded-restart `removeDirRecursive(): RemovalResult`, `protected createIterator()`, `MAX_WALK_ATTEMPTS` constant, PHPDoc.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerRecursiveRemovalTest.php` — **new**: 11 specs.
