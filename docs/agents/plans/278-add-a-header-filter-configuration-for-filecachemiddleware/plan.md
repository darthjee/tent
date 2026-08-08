# Plan: add a header filter configuration for FileCacheMiddleware

Issue: [278-add-a-header-filter-configuration-for-filecachemiddleware.md](../../issues/278-add-a-header-filter-configuration-for-filecachemiddleware.md)

## Overview

Add a new `require_cache_header` configuration option to `FileCacheMiddleware` and `DefaultProxyRequestHandler` — the write-only counterpart to the existing `skip_cache_header`. When configured, a response is only cached if the named header is present in the **response** (request-side presence is never checked, both for correct semantics and to prevent clients from forcing disk writes). When both `skip_cache_header` and `require_cache_header` are configured and both headers are found in the response, `skip_cache_header` wins and the response is not cached — this falls out naturally from combining the conditions with OR, no special-case logic needed.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **New config key**: `require_cache_header` (string, header name), mirroring `skip_cache_header`'s shape exactly.
- **`FileCacheMiddleware`** (`source/source/lib/middlewares/FileCacheMiddleware.php`):
  - `__construct(FolderLocation $location, array $matchers = [], ?string $skipCacheHeader = null, ?RequestHasher $requestHasher = null, ?string $requireCacheHeader = null)` — new `?string $requireCacheHeader = null` param **appended after** `$requestHasher` (preserves existing positional call sites).
  - `build(array $attributes)` reads `$attributes['require_cache_header'] ?? null`.
- **`DefaultProxyRequestHandler`** (`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`):
  - `__construct(string $host, string|false $cache, array $cacheCodes, ?HttpClientInterface $httpClient = null, ?string $skipCacheHeader = null, ?RequestHasher $requestHasher = null, ?array $filterQueryParams = null, ?string $requireCacheHeader = null)` — new param **appended after** `$filterQueryParams`.
  - `build(array $params)` reads `$params['require_cache_header'] ?? null`, threaded through to the `FileCacheMiddleware` constructed in `initializeMiddlewares()`.
- **Behavior**: `require_cache_header` gates only `FileCacheMiddleware::processResponse` (cache write). It checks the response headers only, case-insensitively (same normalization style as the existing `shouldSkipCacheForResponse`, which iterates `Response::headers()` raw header lines with a `strtolower($name) . ':'` prefix match). It never affects `processRequest` (cache read) — a response that was never written to cache for lack of the header simply can't be found on a later read either.
- **Example config shape** (for docs and tests to stay consistent):
  ```php
  'skip_cache_header'    => 'X-Skip-Cache',   // presence in request OR response → don't cache
  'require_cache_header' => 'X-Cache-Allow',  // absence in response → don't cache (response-only)
  ```
