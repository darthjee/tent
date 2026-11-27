# Plan: CacheDirCleaner: harden the base-path guard against '..' segments and intermediate symlinks

Issue: [320-cachedircleaner-harden-the-base-path-guard-against-segments-and-intermediate-symlinks.md](../../issues/320-cachedircleaner-harden-the-base-path-guard-against-segments-and-intermediate-symlinks.md)

## Overview
Harden `CacheDirCleaner::deleteDir()`'s guard with a private, string-only `.`/`..`/NUL segment check and a `realpath(dirname($dir))` vs `realpath($base)` comparison, logging suspicious refusals at `warn`. All work is within the `tent` agent's scope (PHP source, PHPUnit tests, and the matching guide note).

See [tent.md](tent.md) for the full plan.
