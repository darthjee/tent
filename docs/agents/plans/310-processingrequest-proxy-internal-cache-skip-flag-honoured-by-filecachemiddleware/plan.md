# Plan: ProcessingRequest: proxy-internal cache-skip flag honoured by FileCacheMiddleware

Issue: [310-processingrequest-proxy-internal-cache-skip-flag-honoured-by-filecachemiddleware.md](../../issues/310-processingrequest-proxy-internal-cache-skip-flag-honoured-by-filecachemiddleware.md)

## Overview
Add a one-way, proxy-internal `skipCache()` / `cacheSkipped()` flag to `RequestInterface` (real on `ProcessingRequest`, always-false no-op on `Request`). `FileCacheMiddleware` checks it first in `shouldSkipCache()` — covering read, write and both `evictOn` removal branches — and `CacheStalenessMiddleware` gets an explicit first-line guard so no refresh or refresh-time eviction is scheduled. The flag never becomes a header and has no configuration surface. A full bypass is supported only from `prependMiddlewares`, documented and pinned by a negative handler test.

## Agents involved

- [tent](tent.md) — models, middlewares, docblocks and all PHPUnit tests.
- [product-dev-extending](product-dev-extending.md) — developer-facing docs (cache guide, creating-middlewares guide, matchers note).

## Shared contracts

- **API** (`Tent\Models\RequestInterface`):
  - `public function skipCache(): void;`
  - `public function cacheSkipped(): bool;`
- **`ProcessingRequest`**: private `bool $cacheSkipped = false`; `skipCache()` sets it to `true`, one-way (no clear/setter, not in `ATTRIBUTES`, never derived from headers or config).
- **`Request`**: `skipCache()` is a no-op; `cacheSkipped()` always returns `false`.
- **Behaviour with the flag set**: `FileCacheMiddleware` performs no cache read, no write, and no `evictOn` removal; `CacheStalenessMiddleware::processRequest()` returns immediately (no `BackgroundRefresher`, no `.refreshing` sentinel). No header is added to the upstream request or the client response. `CacheCleanupMiddleware` ignores the flag.
- **Placement rule**: a guard calling `skipCache()` must be declared in `prependMiddlewares` for a full (read + write) bypass; in `middlewares` it only bypasses the write path on a `default_proxy` handler (the built-in `FileCacheMiddleware` short-circuits the chain on a hit).
- **Release**: minor (1.3.0), with the upgrade note "`RequestInterface` gained `skipCache()` / `cacheSkipped()`; any external implementer must add them."
