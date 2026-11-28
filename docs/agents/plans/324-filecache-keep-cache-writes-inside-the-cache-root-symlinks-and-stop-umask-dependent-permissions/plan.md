# Plan: FileCache: keep cache writes inside the cache root (symlinks) and stop umask-dependent permissions

Issue: [324-filecache-keep-cache-writes-inside-the-cache-root-symlinks-and-stop-umask-dependent-permissions.md](../../issues/324-filecache-keep-cache-writes-inside-the-cache-root-symlinks-and-stop-umask-dependent-permissions.md)

## Overview

Harden the cache **write** path against symbolic links and permissive umasks. Every file tent writes into the cache tree (body, meta, refresh sentinel) goes through a shared atomic writer (temp file → `chmod 0644` → `rename()`), so a symlink in place of the target is replaced rather than followed. `FileCache::store()` additionally checks, through a containment helper extracted from `CacheDirCleaner`, that the entry directory resolves inside the real cache location before creating or writing anything. Directories are created `0755`. The read path is unchanged. The `tent` specialist implements the code and specs; `product-dev-extending` updates the trust-boundary note in the cache guide.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **Containment helper**: `Tent\Models\FolderLocation::contains(string $path): bool` — `clearstatcache(true)`, then `realpath($path)` must equal `realpath(basePath())` or start with `realpath(basePath()) . '/'`. Returns `false` when either side cannot be resolved. The cache location may itself be a symlink. Pure check, no logging.
- **Atomic writer**: `Tent\Utils\FileUtils::writeAtomically(string $path, string $content): bool` — writes `$path . '.tmp-' . <random hex>` in the same directory, `chmod 0644`, `rename()` over `$path`; removes the temp file on failure; returns `false` on failure, never throws.
- **Modes (fixed, not configurable)**: cache directories `0755` (`FileCache` entry directories and `CacheTrash`'s `.tent-trash`), cache files `0644`.
- **Write-skip log line** (`warn`): `cache store skipped — path: <requestPath>, method: <METHOD>, reason: resolves outside cache location`.
- **Behavior to document**: cache writes never follow symlinks out of the cache location (skipped with the `warn` above); a symlink in place of a cache file is replaced; directories/files are created `0755`/`0644` whatever the umask; **reads still trust the directory** (symlinks are not checked on cache hits), so the cache directory must still be writable only by tent.
