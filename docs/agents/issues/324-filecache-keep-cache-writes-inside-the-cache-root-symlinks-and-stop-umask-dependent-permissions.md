# Issue: FileCache: keep cache writes inside the cache root (symlinks) and stop umask-dependent permissions

## Description

`FileCache` writes cached responses to `<base>/<requestPath>/<METHOD>/<hash>.body.dat` and `.meta.json`, creating intermediate directories with `mkdir($basePath, 0777, true)` and writing with `file_put_contents()`. Two other writers share the cache tree: `CacheStalenessMiddleware` writes a `<hash>.meta.json.refreshing` debounce sentinel with `file_put_contents()`, and `CacheTrash::ensure()` creates `<base>/.tent-trash` with `mkdir(..., 0777)`.

The `..`-segment half of the original idea is **already done by #322**: `FileCache` is inert (no read, no write, no `mkdir`, `warn` logged) whenever the request path or method has a `.`/`..` segment or a NUL byte, via the shared `FileUtils::hasSafeSegments()` lexical check.

#322 explicitly left **symbolic links inside the cache directory** out of scope (planting one requires write access to the cache volume, which already allows cache poisoning), documented the trust boundary in `docs/guides/tent/cache-configuration.md` ("the cache directory must be writable only by tent; symbolic links inside it are not defended against"), and kept the read path free of `realpath()`/`stat` calls.

The delete side is already symlink-aware since #320: `CacheDirCleaner::resolvesInsideBase()` compares `realpath(dirname($dir))` against `realpath($base)`.

### Scope

In scope:

- `FileCache::store()` / `ensureCacheFolderExists()`: containment check, explicit modes, temp-file + `rename()` writes.
- `CacheStalenessMiddleware::writeSentinel()`: write the `.refreshing` sentinel through the same safe writer (no symlink following, `0644`).
- `CacheTrash::ensure()`: create the trash folder with `0755` instead of `0777` (it already refuses a symlink in its place).
- Extracting the shared containment helper and making `CacheDirCleaner` use it (no behavior change there).
- Updating the trust-boundary note in `docs/guides/tent/cache-configuration.md`: writes are now contained; reads still trust the directory.

Out of scope:

- `..`/`.` segments and NUL bytes (done in #322).
- Read-path symlink defence (see "Alternatives rejected").
- `FileCache::remove()`: `unlink` removes a link, never its target.
- Segments longer than `NAME_MAX` (#326).
- Configurable modes: `0755`/`0644` are fixed. A configuration option for group-shared caches could be a follow-up if needed.

## Problem

- **Writes follow symlinks out of the cache volume.** If any directory under the cache root — or the final `<hash>.body.dat` / `.meta.json` file itself — is a symlink, `mkdir -p` and `file_put_contents()` follow it: tent creates directories and writes files, as its own user, wherever the link points. This goes beyond cache poisoning: it reaches outside the cache volume. Cache volumes (Docker volumes, bind mounts) are often shared with other containers or host users, so "writable only by tent" does not always hold.
- **Tent itself can weaken the trust boundary.** `mkdir(..., 0777)` and `file_put_contents()` (0666) leave the final permissions to the process umask. With umask `0` — seen in some container setups — every cache directory and file becomes world-writable, which is exactly how another local user could plant a symlink or poison an entry.

## Expected Behavior

- Cache directories created by tent are at most `0755` and cache files `0644`, whatever the umask.
- A cache store never creates a directory or writes a file outside the real path of the cache location. When the entry directory (or its deepest existing ancestor) resolves outside it, nothing is written, a `warn` is logged (e.g. `cache store skipped — path: …, reason: resolves outside cache location (<real path>)`), and the request/response is unaffected — the same behavior as #322's inert entries.
- A symlink standing in place of `<hash>.body.dat` / `.meta.json` is replaced by a regular file, never followed.
- Cache hits are unchanged: no extra filesystem calls on the read path.

### Edge cases

- **Cache location itself a symlink** (e.g. `/var/cache/tent` → `/data/tent`): allowed; both sides are resolved.
- **Symlink to a directory inside the cache root**: allowed (it resolves inside the root).
- **Race (TOCTOU)**: a link swapped in between the check and the `mkdir`/write is not prevented — the same limitation #320 accepted; the post-`mkdir` re-check narrows the window.
- **Leftover temp files** if the process dies between write and rename: use a recognisable name (e.g. `<hash>.body.dat.tmp-<random>`), never read as entries, removed with the directory on the next invalidation.
- **Concurrent stores of the same entry**: each writes its own temp file; the last `rename()` wins; both results are complete files.
- **Write order**: body renamed first, meta last. `exists()` requires both files, so meta acts as the commit marker and a reader never sees a meta without its body.

### Backward compatibility

- No change for legit traffic or existing cache data; existing entries stay readable.
- Directories and files created from now on are `0755`/`0644` instead of umask-dependent. Setups relying on the cache being group-writable by another process (unusual) need to adjust; call it out in the PR.
- Deployments that intentionally symlink sub-directories of the cache root to *outside* it stop being cached there (with a `warn`); call it out in the PR.
- The new helper is additive; `CacheDirCleaner`'s private method is replaced by it.

## Solution

### 1. Do not weaken the trust boundary (permissions)

- Create cache directories with mode `0755` instead of `0777`. `mkdir`'s mode is reduced by the umask but never widened, so this caps directories at `0755`.
- Set cache files to `0644` explicitly (on the temp file, before the rename).

### 2. Realpath containment check on writes only

In `FileCache::store()` (writes only happen on cache misses, so this stays off the hot read path):

1. **Before `mkdir`**: find the deepest existing ancestor of the entry directory and check that its `realpath()` is, or lies under, `realpath(<cache location>)`, so `mkdir -p` never creates directories outside the root through a link.
2. **After `mkdir`**: check the entry directory itself the same way.
3. **Write body and meta through a temp file + `rename()`**: write to a uniquely named temp file in the entry directory, `chmod` it to `0644`, then `rename()` it over the final name (body first, meta last). `rename()` replaces a symlink standing in place of the target instead of following it; readers never see a partially written body.
4. **On a failed check**: write nothing, log a `warn`, leave the request unaffected.

### 3. Shared safe writer

Extract steps 3 (temp file, `chmod 0644`, `rename()`) into a small shared helper (e.g. `FileUtils::writeAtomically(string $path, string $content): bool`), used by `FileCache::store()` for body and meta and by `CacheStalenessMiddleware::writeSentinel()` for the `.refreshing` sentinel. The sentinel is only written for an existing entry, so its directory already exists and was checked when the entry was stored; it keeps its best-effort semantics (failures ignored). `CacheTrash::ensure()` switches to `mkdir(..., 0755)`.

### 4. Shared containment helper

Extract the containment comparison from `CacheDirCleaner::resolvesInsideBase()` (`clearstatcache`, `realpath` on both sides, `===` or prefix `<realBase>/` match) into a shared helper — e.g. `FolderLocation::contains(string $path): bool` — used by both `CacheDirCleaner` and `FileCache`. The cache location may itself be a symlink; only paths below it are constrained.

### Alternatives rejected

- **Close as covered by #322**: leaves writes outside the cache volume possible whenever the volume is shared, and leaves the umask issue.
- **Realpath check on reads too**: reading through a planted link is no worse than the cache poisoning the attacker can already do, and it would add `realpath()`/`stat` calls to every cache hit, which #322 deliberately avoided.
- **Startup check / warning for a group- or world-writable cache root**: mod_php has no startup phase, so it would run on every request. Could be a follow-up (e.g. a diagnostic command).
- **`O_NOFOLLOW`-style opens**: PHP's `fopen` offers no portable no-follow flag; temp file + `rename()` achieves the same for the final component.

### Testing strategy

With a temporary cache root and a sibling "outside" directory:

1. An intermediate directory under the root is a symlink to the outside directory → `store()` creates nothing and writes nothing outside, logs a `warn`; the response is unaffected.
2. A link pointing to a not-yet-existing path outside → `mkdir` is never attempted outside.
3. `<hash>.body.dat` / `.meta.json` pre-planted as symlinks to outside files → after `store()` the outside files are untouched and the entry holds the new content as regular files.
4. Cache root itself a symlink → storing and reading work normally.
5. With `umask(0)`, created directories are `0755` and files `0644`.
6. `CacheStalenessMiddleware`: a sentinel path pre-planted as a symlink to an outside file → the outside file is untouched and the sentinel is a regular `0644` file; `CacheTrash::ensure()` creates the trash folder as `0755` under `umask(0)`.
7. Shared helper unit specs (inside, equal, outside, prefix look-alike such as `/cache-other` vs `/cache`, unresolvable path); existing `CacheDirCleaner` specs from #320 keep passing.
8. Shared writer specs: no temp files left behind after a successful store.

## Benefits

- Closes writes outside the cache volume through planted symlinks, at the cost of a few `realpath()`/`stat` calls on cache misses only; cache hits are unchanged.
- Tent no longer makes its own cache world-writable under a permissive umask.
- One shared containment check for writes and deletions.
