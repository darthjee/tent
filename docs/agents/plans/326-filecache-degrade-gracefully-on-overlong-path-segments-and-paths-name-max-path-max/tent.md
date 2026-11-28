# Plan: FileCache: degrade gracefully on overlong path segments and paths (NAME_MAX / PATH_MAX)

Issue: [326-filecache-degrade-gracefully-on-overlong-path-segments-and-paths-name-max-path-max.md](../../issues/326-filecache-degrade-gracefully-on-overlong-path-segments-and-paths-name-max-path-max.md)

## Overview
Make `FileCache` silently bypass caching, logging a single `warn`, for request paths that the filesystem cannot hold. Also make every `mkdir()` failure in the store path silent towards the client. Today `ensureCacheFolderExists()` calls `mkdir()` without `@`. With PHP's default `display_errors = 1` in the `php:8.4-apache` image, the warning is printed into the response before tent sets headers. The body is corrupted, the upstream headers are lost and the status is forced to 200.

## Context
- `FileCache::__construct()` already computes `$this->inert` from `hasUnsafePath()` (dot segments / NUL) and `CacheTrash::contains()` (lexical, no filesystem access). An inert entry is never a hit and is never read, stored or removed.
- Entry layout: `<location>/<requestPath>/<METHOD>/<hash>.body.dat` and `<hash>.meta.json`, built by `FileCache::basePath()` and `CacheFilePath::path()`.
- Derived files: `FileUtils::writeAtomically()` writes `<file>.tmp-<16 hex>` (21 extra bytes). `CacheStalenessMiddleware` uses `metaFilePath() . '.refreshing'` (11 extra bytes, `private const REFRESH_SENTINEL_SUFFIX`).
- `requestPath()` is the raw, non-decoded `parse_url(REQUEST_URI)` path. Lengths are measured in bytes (`strlen`) on the exact strings built for the filesystem.
- The location base path (operator config) counts only toward the `PATH_MAX` total, never toward the per-segment check.
- Existing warning format: `cache bypassed — path: <path>, method: <method>, reason: unsafe path`.
- Tests run on PHPUnit 9.6 (`convertWarningsToExceptions` on), on Linux (Docker / cimg), so `PATH_MAX = 4096`. Pattern to copy: `tests/unit/lib/models/FileCache/FileCacheUnsafePathTest.php` (temp root, `CapturingLoggerInstance`, `listTree()`).
- Out of scope: `display_errors` in the production image (#329), a global error handler, and caching overlong paths.

## Steps

- [01 — Add a PHP-warning capturing test helper](tent/01-warning-catcher-helper.md)
- [02 — Up-front length check in FileCache](tent/02-filecache-length-check.md)
- [03 — mkdir backstop](tent/03-mkdir-backstop.md)
- [04 — FileCache specs](tent/04-filecache-specs.md)
- [05 — Cleaner, resolver, staleness and middleware specs](tent/05-cleaner-resolver-middleware-specs.md)
- [06 — User docs](tent/06-user-docs.md)

## CI Checks
- `source`: `composer tests:unit` inside `make tests` (`docker compose run tent_tests`) (CI job: `unit_test`, which runs `composer coverage`)
- `source`: `composer lint` (phpcs + phpmd) (CI job: `checks`)

## Notes
- **Manual acceptance (dev environment)**: through `docker-compose`, `curl -si` a URL with a 300-byte segment on a cached route. Check that:
  - the upstream status, headers and body come back intact, with no `<b>Warning</b>` prefix;
  - a single `path too long` warn is logged;
  - nothing is created under the cache location.
- **Boundary specs need Linux** (`PATH_MAX = 4096`). They will not behave the same on macOS (1024); run them in the container.
- **Entries written before #324** with a leaf name of 235–255 bytes, or a path of 4075–4095 bytes, become inert, and their files stay on disk until a directory invalidation. This is accepted; document it in the `FileCache` docblock.
- **Known limitation, not addressed**: with a relative location whose absolute form exceeds `PATH_MAX`, `realpath()` in `FolderLocation::contains()` fails silently. The store is skipped with a misleading `resolves outside cache location` warn.
- **Truncation and UTF-8**: `substr` on 200 bytes may cut a multibyte UTF-8 character in the log line. This is acceptable, since the raw path is normally percent-encoded.
