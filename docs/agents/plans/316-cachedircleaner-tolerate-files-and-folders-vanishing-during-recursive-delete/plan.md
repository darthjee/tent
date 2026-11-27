# Plan: CacheDirCleaner: tolerate files and folders vanishing during recursive delete

Issue: [316-cachedircleaner-tolerate-files-and-folders-vanishing-during-recursive-delete.md](../../issues/316-cachedircleaner-tolerate-files-and-folders-vanishing-during-recursive-delete.md)

## Overview

Introduce `Tent\Utils\RemovalResult` (enum) and `Tent\Utils\SafeRemover` (tolerant `unlink`/`rmdir` with existence re-check and self-logged `warn` on real failures), then route `CacheDirCleaner`'s recursive delete and `FileCache::remove()` through it. `CacheDirCleaner` gains a bounded walk restart, a symlinked-root rule, a non-directory no-op, and a `protected createIterator()` test seam. All work is inside `source/`, owned by the `tent` agent.

See [tent.md](tent.md) for the full plan.
