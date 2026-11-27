# Plan: FileCache: never read or write outside the cache root through dot segments

Issue: [322-filecache-never-read-or-write-outside-the-cache-root-through-dot-segments.md](../../issues/322-filecache-never-read-or-write-outside-the-cache-root-through-dot-segments.md)

## Overview
Extract #320's private `CacheDirCleaner::hasSafeSegments()` into a public `FileUtils::hasSafeSegments()` and use it as the single definition of an unsafe cache path. `FileCache` folds an unsafe path or method into its existing inert flag (renamed from `$reserved` to `$inert`), so the request is proxied uncached and nothing is read, written or created. `CacheDirResolver` returns `null` for unsafe paths (`resolveExact()` becomes `?string`), and `CacheDirCleaner::clean()`/`cleanPath()` refuse them up front with a `warn`. The developer docs gain a "Path safety" section, and the Apache behaviour is verified manually for the PR description.

## Agents involved

- [tent](tent.md) — `FileUtils`, `FileCache`, `CacheDirResolver`, `CacheDirCleaner`, docblocks, all PHPUnit tests, and the manual Apache verification.
- [product-dev-extending](product-dev-extending.md) — "Path safety" section in the cache configuration guide.

## Shared contracts

- **API** (`Tent\Utils\FileUtils`): `public static function hasSafeSegments(string $path): bool`. Returns `false` when the path contains a NUL byte, or when any `/`-separated segment is exactly `.` or `..`; `true` otherwise. Purely lexical; whole-segment match only (`...`, `..foo`, `a..b`, `.git`, `%2e%2e`, `\` are safe). Same semantics as the current private `CacheDirCleaner::hasSafeSegments()`, which is removed.
- **`FileCache`**: unsafe when `FileUtils::hasSafeSegments(<requestPath> . '/' . <requestMethod>)` is false. An unsafe entry is inert, like a trash entry: `exists()` false, `timestamp()` null, `content()`-path never read, `store()` and `remove()` no-ops, no directory created. Logs `Logger::warn('cache bypassed — path: <path>, method: <method>, reason: unsafe path')` from the constructor.
- **`CacheDirResolver`**: `resolve()` (already `?string`) and `resolveExact()` (now `?string`) return `null` for an unsafe path.
- **`CacheDirCleaner`**: `clean()` and `cleanPath()` check `FileUtils::hasSafeSegments($path)` first; on failure they log `Logger::warn('cache cleanup refused — path: <path>, reason: unsafe path')`, touch nothing, and return (the orphan sweep still runs, as today on any other no-op).
- **Out of scope**: symlinks inside the cache directory (trust boundary: writable only by tent), normalizing paths, answering 400, `NAME_MAX` (#326).
