# Plan: CacheDirCleaner: harden the base-path guard against '..' segments and intermediate symlinks

Issue: [320-cachedircleaner-harden-the-base-path-guard-against-segments-and-intermediate-symlinks.md](../../issues/320-cachedircleaner-harden-the-base-path-guard-against-segments-and-intermediate-symlinks.md)

## Overview
Make the `CacheDirCleaner` guard enforce the invariant it documents — only directories genuinely under the configured cache location are ever deleted — by checking where a path really points before any filesystem mutation. `CacheDirResolver`, `FileCache` (tracked in #324) and the check-to-`rename()` TOCTOU window are out of scope.

## Context
- `deleteDir()` today only does `str_starts_with($dir, $base . '/')` plus `$this->trash->contains($dir)`, then #316's `is_link()` root handling and #319's rename-into-trash (`trashDir()`).
- `$dir` comes from `CacheDirResolver`, which drops empty segments but passes `.`/`..` through, so `cleanPath('/..')` → `<base>/../GET` and `clean('collection', '/../x')` → `<base>/../GET` pass the prefix check.
- A symlink in a non-final component (`cache/link` → `/elsewhere`) passes both checks; the rename (or the in-place fallback) then deletes `/elsewhere/.../GET`.
- Nothing in the proxy URL-decodes request paths, and `PlaceholderPattern` values can never contain `.` or `/`.

## Steps

- [01 — Harden the deleteDir() guard](tent/01-harden-deletedir-guard.md)
- [02 — Add CacheDirCleanerGuardTest](tent/02-add-guard-tests.md)
- [03 — Rewrite the two weak outside-base tests](tent/03-rewrite-weak-outside-base-tests.md)
- [04 — Document the constraints in the middleware guide](tent/04-document-guide-constraints.md)

## CI Checks
- `source`: `composer coverage` (CI job: tent tests — runs `vendor/bin/phpunit ... tests`), run inside the test container via `make tests`
- `source`: `composer lint` (CI job: tent lint — `phpcs --standard=phpcs.xml` + `phpmd source,tests text phpmd_unusedcode.xml`)

## Notes
- Keep the guard a **private** method on `CacheDirCleaner`; extracting a shared `FolderLocation::contains()` is #324's decision.
- Compare against `realpath($base)`, never the raw base string — the base is often a symlink (Docker volume). Never resolve the final component (preserves #316).
- Keep the `realpath` comparison exact (no lowercasing, unlike `CacheTrash::contains()`).
- Run the segment check (which rejects `\0`) **before** `realpath()`: PHP 8 filesystem functions throw `ValueError` on NUL bytes.
- Existing `Rename`, `OrphanSweep` and `RecursiveRemoval` tests must keep passing unchanged; `CacheDirResolver` tests are untouched.
- Accepted breakages (documented in step 04): literal `..` in `custom` targets, and deliberately symlinked cache subdirectories, are now refused with `warn`.
