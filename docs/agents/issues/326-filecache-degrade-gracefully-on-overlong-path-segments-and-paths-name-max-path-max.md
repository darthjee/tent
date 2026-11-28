# Issue: FileCache: degrade gracefully on overlong path segments and paths (NAME_MAX / PATH_MAX)

## Description

`FileCache::basePath()` builds the cache directory as `<base>/<requestPath>/<METHOD>` and creates it with `mkdir(..., 0755, true)` (in `ensureCacheFolderExists()`, without `@`). Each request-path segment becomes a directory name as-is.

Spotted while refining #322.

### Current behavior (checked on `php:8.4-apache`)

- On a path segment longer than `NAME_MAX` (255 bytes), `file_exists()`, `is_dir()` and `is_link()` simply return `false`, so `exists()` / `readMeta()` already behave as a cache miss.
- `store()` reaches `mkdir()`, which raises `Warning: mkdir(): File name too long` (or `Warning: mkdir(): Invalid path` for a full path longer than `PATH_MAX`, 4096). `prepareCacheFolder()` then returns `false`, so nothing is written.
- `FileUtils::writeAtomically()`, `CacheTrash` and `CacheDirCleaner` already silence their filesystem calls with `@`; leaf file names are hash-derived, so of fixed length.
- Apache's default `LimitRequestLine` (8190) lets a client send both overlong segments and paths longer than `PATH_MAX`.

## Problem

The warning raised by `mkdir()` is not harmless. The production image runs with PHP's built-in `display_errors = 1` (see #329), and `store()` runs in `processResponse`, before tent emits the response. Reproduced in a stock `php:8.4-apache` container:

```
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8

<b>Warning</b>: mkdir(): File name too long in <b>/var/www/html/index.php</b> on line <b>2</b>
<b>Warning</b>: Cannot modify header information - headers already sent ...
<b>Warning</b>: http_response_code(): Cannot set response code - headers already sent ...
BODY
```

So any client can corrupt the proxied response just by requesting a URL with an overlong segment on a cached route:

- the body is prefixed with HTML warning text;
- every upstream header is dropped (Content-Type, cookies, cache headers, ...);
- the status is forced to 200 (an upstream 404/500 is reported as 200);
- internal server file paths are disclosed.

This is a robustness concern, not a traversal one: nothing escapes the cache root.

## Expected Behavior

- A request whose path, method or derived cache file names exceed `NAME_MAX` (255 bytes) or `PATH_MAX` (4096 bytes) is proxied normally: the client receives the upstream response byte-identical (body, headers, status).
- No PHP warning, notice or exception is raised by the cache layer, whatever `display_errors` is set to.
- Nothing is cached, read, created or removed for such a request; a single `warn` (`reason: path too long`, path truncated) is logged.
- Any other `mkdir()` failure while storing an entry (lower filesystem limits, permissions, full or read-only disk) is also silent towards the client and logged as a `warn`.

## Solution

1. `FileCache` must never raise a PHP warning (or exception) because of an overlong request path. Such requests degrade to a cache bypass: proxied normally, response byte-identical to the upstream one, nothing cached, a single `warn` log line at most.
2. Check `CacheDirResolver` / `CacheDirCleaner` for the same behavior on overlong segments and paths.
3. Add specs covering overlong segments and overlong full paths, including one asserting that no PHP warning is emitted (see Testing strategy).

### Approach

**Main fix: up-front length check, entry marked inert.** In the `FileCache` constructor, next to `hasUnsafePath()`, the entry is checked against hard-coded byte limits (class constants, e.g. `NAME_MAX = 255`, `PATH_MAX = 4096`; lengths measured with `strlen()`, not `mb_strlen()`):

- every segment of `<requestPath>/<METHOD>` must be at most `NAME_MAX` bytes;
- the longest file path derived from the entry must stay below `PATH_MAX` (which includes the terminating NUL). The longest derived file is currently a body/meta file plus the `.tmp-<16 hex>` suffix of `FileUtils::writeAtomically()` (21 bytes, longer than the `.refreshing` sentinel of `CacheStalenessMiddleware`). The check should be expressed so that adding a longer derived suffix later cannot silently break it.

When either check fails, the entry is inert, like an unsafe or trashed one: never a hit, nothing read, stored or removed, and the filesystem is not touched. A single `warn` is logged, following the existing format: `cache bypassed — path: <path>, method: <method>, reason: path too long`.

**Backstop: `@mkdir` + `warn`.** `ensureCacheFolderExists()` silences `mkdir()` and, when it fails, logs a `warn` with the reason from `error_get_last()`. This covers anything the up-front check cannot predict: filesystems with lower limits (e.g. eCryptfs, about 143 bytes per name), permission denied, a full disk, a read-only filesystem. All of these currently leak into the response the same way.

#### Rejected alternatives

- **`@mkdir` only (reactive)**: the filesystem is still touched, the log reason is a raw OS message, and the staleness sentinel is not covered. Kept as a backstop only.
- **Hashing overlong segments so they are still cached**: it changes the directory layout, so `CacheDirResolver` invalidation would have to apply the same mapping. It risks name collisions and still needs a fallback for `PATH_MAX`. That is too much complexity for requests that are rare and often abusive.
- **Runtime limits via `posix_pathconf()` / configurable limits**: `posix_pathconf()` needs an existing path and the posix extension. Configurable limits give little benefit, as the backstop already covers filesystems with lower limits.

### Edge cases

- **Measure what reaches the filesystem.** `requestPath()` is the raw, still percent-encoded path from `parse_url(REQUEST_URI)`, so `%41` takes 3 bytes on disk. The check measures, in bytes (`strlen()`), exactly the strings built by `basePath()` / `CacheFilePath::path()`, never a re-derived or decoded version. Multibyte UTF-8 is covered for free.
- **Duplicate / empty slashes.** `FileUtils::getFullPath()` only trims outer slashes, so `a//b` is kept. The kernel compares the raw string against `PATH_MAX`, so the full-path check counts every byte as built, duplicate slashes included. Empty segments have length 0 and pass the segment check.
- **What the `NAME_MAX` segment check covers:**
  - every request-path segment;
  - the method (Apache accepts arbitrary extension-method tokens, so an overlong method is reachable);
  - the leaf file name: hash + longest derived suffix (`.body.dat` / `.meta.json` + `.tmp-<16 hex>`). This way a custom `RequestHasher` returning a long hash is bypassed with a `warn` rather than failing silently in `writeAtomically()`.
- **Cache location base path.** It comes from operator config, so it is *not* subject to the segment check (a misconfigured location would otherwise bypass and `warn` on every request). It only counts toward the `PATH_MAX` total.
- **Boundaries.**
  - A 255-byte segment / leaf passes; 256 is bypassed.
  - The longest derived file path passes at 4095 bytes and is bypassed at 4096 (`PATH_MAX` includes the terminating NUL).
- **Check order.** The unsafe-path check (`hasUnsafePath()`) runs first. An entry that is both unsafe and overlong logs a single `warn`, with reason `unsafe path`.
- **Known limitation: relative location.** With a relative location (e.g. `./cache`), the measured string is the relative one, which matches what the kernel checks. However, `FolderLocation::contains()` resolves through `realpath()`. If the *absolute* form exceeds `PATH_MAX`, it returns `false` silently, and the store is skipped with a misleading `resolves outside cache location` warn. This is harmless (no warning, nothing written) and not addressed here.

### Backward compatibility

- **On-disk layout**: unchanged for every entry within the limits.
- **Overlong requests**: today they get a corrupted response (warning prefix, upstream headers lost, status forced to 200); after the fix they get the upstream response unchanged. This is the intended fix, not a regression.
- **Logs**: the leaked PHP warning (`mkdir(): File name too long` / `Invalid path`) is replaced by a `Logger` `warn` (`reason: path too long`, or the `error_get_last()` reason for the `mkdir` backstop).
- **Entries written before #324 (accepted, to be documented).** Before #324, files were written directly, with no `.tmp-<16 hex>` suffix. Entries from that time could have:
  - a leaf name of 235–255 bytes (only with a custom `RequestHasher`), or
  - a body/meta path of 4075–4095 bytes.

  The headroom-aware check makes those entries inert: permanent cache misses, never re-stored, and left on disk since `remove()` does nothing for an inert entry. They are cleared by the next `CacheDirCleaner` invalidation of their directory, which does not go through `FileCache`, or by a manual purge. Current tent cannot create such entries, since `writeAtomically()` already fails on them. A single `inert` flag is kept rather than separate read/write limits, as the affected window is close to empty. The `FileCache` docblock should mention this.

### Performance & security

- **Cost of the check**: a few `strlen()` calls and an `explode()` in the constructor, so negligible. It runs before any filesystem access, so an overlong request now skips the `file_exists` / `is_dir` calls and the ancestor walk (`file_exists` / `is_link` per level) that today precede the failing `mkdir()`.
- **Upstream load**: bypassing overlong paths gives an attacker nothing new. A unique query string already forces a miss with `QueryRequestHasher`.
- **Security gain**: no more disclosure of server file paths in response bodies, and no more client-triggered tampering with the proxied status and headers.
- **Log volume amplification**: the logged path of the new `path too long` warn is truncated to its first 200 bytes, followed by `… (<N> bytes)`. Otherwise every maximum-length URL (up to about 8 KB under Apache's default `LimitRequestLine`) would log an 8 KB line. The existing warns (`unsafe path`, `resolves outside cache location`) are left unchanged. The `mkdir` backstop warn is only reached for paths that passed the length check, so it is bounded already.
- **`mkdir` backstop under a full disk or read-only filesystem**: every cached-route request logs a `warn` while the condition lasts. This is accepted as a genuine operational signal, and responses are unaffected.
- **Out of scope**: log rate limiting, and log injection through the request path (the path is the raw `REQUEST_URI` path, which cannot carry raw CR/LF, same as the existing logs).

### Testing strategy

The suite runs on PHPUnit 9.6 (Linux, in Docker / cimg), where `convertWarningsToExceptions` is on by default. A plain `store()` on an overlong path therefore already fails today. PHPUnit 10+ no longer converts warnings, so the specs must not rely on that default. Boundary specs assume Linux's `PATH_MAX` (4096); macOS uses 1024.

- **No-warning helper**: a test-support helper (e.g. under `tests/support/`) installs a capturing `set_error_handler`, restores it in `tearDown`, and asserts that no PHP warning or notice was raised. Every spec below uses it.
- **`FileCacheOverlongPathTest`** (modelled on `FileCacheUnsafePathTest`: temp root, `CapturingLoggerInstance`, `listTree()` before/after). A data provider covers an overlong segment, an overlong method, an overlong full path and an overlong leaf name (custom `RequestHasher`). For each case:
  - `exists()` is false, `timestamp()` is null, and `content()` returns `''`;
  - `store()` leaves the tree unchanged and raises no warning;
  - `remove()` does nothing;
  - exactly one `warn` with `reason: path too long` is logged.
- **Boundaries**:
  - a 255-byte segment is stored and a 256-byte one is bypassed;
  - a longest derived path (meta file + `.tmp-<16 hex>`) of 4095 bytes is stored and 4096 is bypassed. The path is built from several 200-byte segments plus one sized against the temp root's length;
  - a custom hash of 224 chars is stored and 225 is bypassed (`.meta.json` + `.tmp-<16 hex>` = 31 bytes).
- **Check order**: a path that is both unsafe and overlong logs a single `warn`, with reason `unsafe path`.
- **Log truncation**: the `path too long` warn contains the first 200 bytes of the path followed by `… (<N> bytes)`, and its length is bounded.
- **`mkdir` backstop**: a regular file standing where an entry directory is needed makes `mkdir()` fail. The spec checks: no PHP warning, one `warn` with the `error_get_last()` reason, nothing written. This works even when tests run as root, unlike permission-based setups.
- **`CacheDirCleaner` / `CacheDirResolver`**: with overlong input, `clean()`, `cleanPath()`, `resolve()` and `resolveExact()` raise no warning or exception and remove nothing.
- **`FileCacheMiddleware`**: with an overlong path, `processRequest()` is a miss, and `processResponse()` returns the response unchanged (body, headers, status) and writes nothing.

#### Manual acceptance (dev environment)

There is no integration suite, and the real symptom is at the HTTP level. So, through `docker-compose`, `curl -si` a URL with a 300-byte segment on a cached route, and check:

- the upstream status, headers and body come back intact, with no `<b>Warning</b>` prefix;
- a single `path too long` warn is logged;
- nothing is created under the cache location.

### Scope

#### In scope

- **`FileCache` store path**: the confirmed `mkdir()` warning. This is the main fix.
- **Length headroom for derived files**: the overlong check must leave room for every file derived from the entry path, not only the body/meta files:
  - the `.tmp-<16 hex>` suffix added by `FileUtils::writeAtomically()`;
  - the refresh sentinel of `CacheStalenessMiddleware` (`metaFilePath()` + `REFRESH_SENTINEL_SUFFIX`).

  Otherwise an entry stored a few bytes under `PATH_MAX` would make the sentinel path exceed it and warn on a stale hit.
- **Invalidation paths (`CacheDirCleaner` / `CacheDirResolver`)**: check and spec their behavior on overlong input. They look already silent (they use `@rename`, `@scandir`, `realpath()` and `file_exists()`), so fix them only if they turn out to warn.

#### Out of scope

- Turning `display_errors` off in the production image is tracked separately in #329. It is complementary, not a substitute: #326 must hold even with `display_errors` on, since the cache must not rely on PHP's error configuration to stay silent.
- A global `set_error_handler` routing every PHP warning to `Logger`.
- Actually caching requests with overlong paths. They are bypassed (see Approach).

## Benefits

- Clients can no longer corrupt proxied responses (status, headers, body) with a long URL on a cached route.
- No more disclosure of internal server paths in response bodies.
- Overlong requests skip all cache filesystem access, and log volume stays bounded.
- The cache stays correct independently of PHP's error-display configuration (complementing #329).
