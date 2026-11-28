# Plan: FileCache: degrade gracefully on overlong path segments and paths (NAME_MAX / PATH_MAX)

Issue: [326-filecache-degrade-gracefully-on-overlong-path-segments-and-paths-name-max-path-max.md](../../issues/326-filecache-degrade-gracefully-on-overlong-path-segments-and-paths-name-max-path-max.md)

## Overview
`FileCache` gets an up-front byte-length check. It makes an entry inert, logging a truncated `path too long` warning, when a request-path segment, the method, or a cache leaf file name exceeds `NAME_MAX` (255), or when the longest derived file path reaches `PATH_MAX` (4096). `mkdir()` also gets an `@` + `warn` backstop, so the cache layer never emits a PHP warning that could corrupt the proxied response. The work is covered by specs, including an explicit no-warning helper, and by user docs. All work lives in `source/` and `docs/guides/tent/`, owned by the `tent` agent.

See [tent.md](tent.md) for the full plan.
