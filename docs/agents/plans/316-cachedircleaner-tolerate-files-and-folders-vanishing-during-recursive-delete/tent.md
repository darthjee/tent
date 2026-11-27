# Plan: CacheDirCleaner: tolerate files and folders vanishing during recursive delete

Issue: [316-cachedircleaner-tolerate-files-and-folders-vanishing-during-recursive-delete.md](../../issues/316-cachedircleaner-tolerate-files-and-folders-vanishing-during-recursive-delete.md)

## Overview

Make cache deletion tolerant of concurrent writers (darthjee/citadel#41): *already gone* is success, *cannot be removed* is a single `warn` line, and nothing ever throws out of `CacheDirCleaner::clean()` / `cleanPath()` or `FileCache::remove()`. The issue file is the source of truth for every semantic detail; this plan orders the work and pins file-level changes.

## Context

- `CacheDirCleaner` (`source/source/lib/content/CacheDirCleaner.php`) today does `is_dir()` then an unguarded CHILD_FIRST SPL walk with bare `unlink()` / `rmdir()`; the iterator throws `UnexpectedValueException` when a subdirectory (or the root) vanishes.
- `FileCache::remove()` (`source/source/lib/content/FileCache.php:241`) does `FileUtils::exists()` then bare `unlink()` for body and meta files.
- `source/source/loader.php` loads classes explicitly via `require_once` (no runtime PSR-4); the utils group used by early classes sits at lines 30–31, before `lib/content/CacheDirResolver.php` / `CacheDirCleaner.php` (lines 35–36) and `FileCache.php` (line 49).
- Logging: `Tent\Log\Logger::warn()` / `::debug()`; tests swap the backend with `Logger::setInstance(new NullLoggerInstance())` or a `$this->createMock(LoggerInstance::class)`.
- Runtime is PHP 8.4 (`php:8.4-apache`); enums are available. `RemovalResult` is the first enum in the codebase.
- Lint: `composer lint` = `phpcs --standard=phpcs.xml` (PSR-12 + Slevomat unused vars/uses + `Squiz.Commenting.FunctionComment` on `source/`) and `phpmd ... phpmd_unusedcode.xml`. `@unlink()` is already lint-clean (`CacheStalenessMiddleware::removeSentinel()`).

## Steps

- [01 — Add RemovalResult and SafeRemover](tent/01-add-removalresult-and-saferemover.md)
- [02 — Make CacheDirCleaner tolerant](tent/02-make-cachedircleaner-tolerant.md)
- [03 — Route FileCache::remove() through SafeRemover](tent/03-route-filecache-remove-through-saferemover.md)

## CI Checks

- `source/`: `composer coverage` (CI job: `unit_test`)
- `source/`: `composer lint` (CI job: `checks`)

## Notes

- Out of scope (do not touch): `CacheDirResolver`, `Cache`, every middleware including `CacheStalenessMiddleware::removeSentinel()`; the base-path prefix guard stays byte-for-byte (its hardening is darthjee/tent#320); rename-then-delete is darthjee/tent#319.
- The 11 `CacheDirCleanerCleanTest` + 6 `CacheDirCleanerCleanPathTest` specs must pass **unmodified** — in particular the `cache cleared — dir: …` debug line on removal and no log when the dir did not exist.
- Never rely on real filesystem races inside the SPL walk or on `chmod` in tests — use the `createIterator()` override and the "directory path to `removeFile()`" / "non-empty dir to `removeDir()`" tricks described in the issue's Testing section.
- `error_get_last()` must be read immediately after the suppressed call, before the existence re-check; fall back to `unknown error` when it returns `null`. Calling `error_clear_last()` just before the `@` call avoids reporting a stale error.
- `phpmd_unusedcode.xml` may flag an unused `createIterator()` parameter or private constant if wired oddly — keep both used.
