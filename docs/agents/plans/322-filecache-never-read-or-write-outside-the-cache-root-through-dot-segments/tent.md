# Tent Plan: FileCache: never read or write outside the cache root through dot segments

Main plan: [plan.md](plan.md)

## Shared contracts

- `FileUtils::hasSafeSegments(string $path): bool` — public, lexical, whole-segment `.`/`..` plus NUL byte; the private copy in `CacheDirCleaner` is removed.
- `FileCache` is inert when `hasSafeSegments(<requestPath> . '/' . <requestMethod>)` is false; it logs `cache bypassed — path: <path>, method: <method>, reason: unsafe path` at `warn`.
- `CacheDirResolver::resolve()` / `resolveExact()` return `null` for an unsafe path; `resolveExact()` becomes `?string`.
- `CacheDirCleaner::clean()` / `cleanPath()` log `cache cleanup refused — path: <path>, reason: unsafe path` at `warn` and do nothing for an unsafe path.

## Steps

- [01 — Extract FileUtils::hasSafeSegments()](tent/01-extract-has-safe-segments.md)
- [02 — FileCache: inert on unsafe path or method](tent/02-file-cache-inert.md)
- [03 — CacheDirResolver and CacheDirCleaner: refuse unsafe paths up front](tent/03-resolver-and-cleaner.md)
- [04 — Middleware-level spec and Apache verification](tent/04-middleware-spec-and-apache-check.md)

## CI Checks
- `source`: `composer coverage` (CI job: `unit_test`)
- `source`: `composer lint` (CI job: `checks`)

## Notes
- `FileCache` is constructed more than once per request (`FileCacheMiddleware::processRequest()`/`processResponse()`, `CacheStalenessMiddleware`), so the `warn` may appear up to once per construction. That is acceptable; do not add cross-instance state just to deduplicate it.
- `CacheStalenessMiddleware` builds a `FileCache` without the `requestMethods` filter, which is why the method has to be checked too.
- Building the body/meta path strings for an unsafe entry is harmless (no filesystem access); only the filesystem operations must be gated.
- The `resolveExact()` return-type change must be called out in the PR description, together with the operator note about entries written outside the root before the fix (see the issue's "Backward compatibility").
