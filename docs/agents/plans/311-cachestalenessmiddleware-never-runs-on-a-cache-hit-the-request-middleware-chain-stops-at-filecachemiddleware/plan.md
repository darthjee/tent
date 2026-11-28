# Plan: CacheStalenessMiddleware never runs on a cache hit: the request-middleware chain stops at FileCacheMiddleware

Issue: [311-cachestalenessmiddleware-never-runs-on-a-cache-hit-the-request-middleware-chain-stops-at-filecachemiddleware.md](../../issues/311-cachestalenessmiddleware-never-runs-on-a-cache-hit-the-request-middleware-chain-stops-at-filecachemiddleware.md)

## Overview

`FileCacheMiddleware` records a `CacheHit` marker (the served `FileCache` entry plus its `HeaderFilter`) on `ProcessingRequest` when it serves a cache hit. `CacheStalenessMiddleware` moves its staleness check from `processRequest()` to `processResponse()` — which `RequestHandler` already runs for every middleware, cache hit included — and acts only on marked hits, scheduling the `BackgroundRefresher` with the marker's header filter. `RequestHandler` and `Middleware` are not changed. The staleness middleware's own header-filter options are deprecated (accepted, ignored, warning logged). Docs are updated accordingly.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **`Tent\Content\CacheHit`** (new, `source/source/lib/content/CacheHit.php`): immutable value object.
  - `__construct(FileCache $cache, HeaderFilter $headerFilter)`
  - `cache(): FileCache`
  - `headerFilter(): HeaderFilter`
- **`ProcessingRequest`** gains `setCacheHit(CacheHit $hit): CacheHit` and `cacheHit(): ?CacheHit` (null by default). `RequestInterface` is **not** changed.
- **`FileCacheMiddleware::processRequest()`** calls `setCacheHit()` only when it serves a cached response (not on miss, eviction-on-read, matcher mismatch, or `skipCache()`/skip header).
- **`CacheStalenessMiddleware`**:
  - `processRequest()`: no longer overridden (inherited pass-through).
  - `processResponse(Response $response): Response`: returns `$response` unmodified; schedules a refresh only when `$response->request()` is a `ProcessingRequest`, not `cacheSkipped()`, has a `cacheHit()`, the entry timestamp is non-null and `time() - timestamp > maxAgeSeconds`. Refresh uses `$hit->cache()` and `$hit->headerFilter()`.
  - Config keys unchanged: `location`, `host`, `maxAgeSeconds`/`max_age_seconds`, `evictOn`/`evict_on`. Deprecated (accepted, ignored, `Logger::warn` once at build when present): `mode`, `excluded_headers`, `additional_excluded_headers`, `allowed_headers`. Constructor signature unchanged; the header-filter parameters are `@deprecated`.
  - Placement: works anywhere in the same handler (after `FileCacheMiddleware`, before it, or in `prependMiddlewares`); docs still recommend "after `FileCacheMiddleware`, same `location`".
  - Known consequence: on the shipped `php:8.4-apache` (mod_php) image the refresh runs synchronously before the response is returned.

## Notes

- Architect follow-ups (outside both specialists' scopes): reword `docs/agents/architecture.md` (~line 88) to "pairs with `FileCacheMiddleware` (same `location`); acts in the response phase on cache hits"; and include the release note in the PR description / GitHub release text (the repo has no `CHANGELOG` file): stale-while-revalidate now actually runs; on mod_php a stale hit waits for a synchronous refresh; staleness header-filter options are deprecated.
- Out of scope: the never-expiring `.refreshing` sentinel (#331).
