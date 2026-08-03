# Issue: Add custom cache hash generator

## Description

Today, `CacheFilePath::path()` (`source/source/lib/utils/CacheFilePath.php`) generates the file name used for cached responses by hashing only the request's query string (`hash('sha256', $query)`). This is used by `FileCacheMiddleware` (`source/source/lib/middlewares/FileCacheMiddleware.php`) and, transitively, by `DefaultProxyRequestHandler` (`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`), which builds a `FileCacheMiddleware` internally as part of its default middleware stack.

## Problem

There is no way for a developer to customize how the cache key is computed. Because the hash is derived purely from the query string, a developer cannot key cache entries on anything else about the request — most notably headers — so there is no way to safely cache responses to private/authenticated requests (where the correct cached response depends on, e.g., an auth header) without different callers overwriting each other's cache entries.

## Expected Behavior

Developers can plug in a custom hash generator on both `FileCacheMiddleware` and `DefaultProxyRequestHandler`, receiving the full request (not just the query string) — including headers — so they can key cache entries however is appropriate for their use case, including caching private/authenticated requests. When no custom generator is configured, behavior stays the same as today (hashing the query string).

## Solution

### Generator contract

No such abstraction exists today: `CacheFilePath::path()` (`CacheFilePath.php`) hashes only the raw query string inline (`hash('sha256', $query)`), and `FileCache` is the one that extracts `$request->query()` before calling it. This needs a genuine new interface, e.g. `Tent\Cache\RequestHasher`:

```php
namespace Tent\Cache;

use Tent\Models\RequestInterface;

interface RequestHasher
{
    public function hash(RequestInterface $request): string;
    public static function build(array $params): self;
}
```

Default implementation:

```php
namespace Tent\Cache;

class QueryRequestHasher implements RequestHasher
{
    public function hash(RequestInterface $request): string
    {
        return hash('sha256', $request->query());
    }

    public static function build(array $params): self
    {
        return new self();
    }
}
```

### Configuration surface

Following the project's existing `class`-key convention for pluggable strategies (same pattern as `RequestResponseMatcher::buildMatchers()`), both `FileCacheMiddleware` and `DefaultProxyRequestHandler` gain a `request_hasher` config key and a matching constructor param, defaulting to `QueryRequestHasher` when omitted.

`FileCacheMiddleware`:

```php
public function __construct(
    FolderLocation $location,
    array $matchers = [],
    ?string $skipCacheHeader = null,
    ?RequestHasher $requestHasher = null
) {
    $this->location = $location;
    $this->matchers = $matchers;
    $this->skipCacheHeader = $skipCacheHeader;
    $this->requestHasher = $requestHasher ?? new QueryRequestHasher();
}

public static function build(array $attributes): FileCacheMiddleware
{
    $location = new FolderLocation($attributes['location']);
    $matchers = RequestResponseMatcher::buildMatchers($attributes['matchers'] ?? []);
    $skipCacheHeader = $attributes['skip_cache_header'] ?? null;
    $requestHasher = isset($attributes['request_hasher'])
        ? $attributes['request_hasher']['class']::build($attributes['request_hasher'])
        : null;

    return new self($location, $matchers, $skipCacheHeader, $requestHasher);
}
```

Config usage:

```php
'middlewares' => [
    [
        'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
        'location' => './cache',
        'request_hasher' => [
            'class' => 'App\\Cache\\AuthAwareRequestHasher',
        ],
    ],
],
```

`DefaultProxyRequestHandler` gets the same `request_hasher` param/key, and passes it through to the `FileCacheMiddleware` it builds internally in `initializeMiddlewares()`.

### `CacheFilePath` signature change

`CacheFilePath::path()` stops computing the hash itself and instead accepts the already-computed hash string, staying a dumb path formatter:

```php
public static function path(string $type, string $basePath, string $hash): string
```

### Where the computed hash is cached: `ProcessingRequest`, not `RequestInterface`

`RequestInterface` is implemented by three classes (`Request`, `ProcessingRequest`, `StaticFileHandler`), only one of which (`ProcessingRequest`) ever flows through the cache middlewares — widening the general interface with a caching-specific concept would leak one feature's concern into an abstraction two unrelated implementers share.

Instead, following the existing precedent of `response()`/`setResponse()`/`hasResponse()` on `ProcessingRequest` (methods that already exist beyond what `RequestInterface` declares), add:

```php
cacheHash(): ?string
setCacheHash(string $hash): string
```

only to `ProcessingRequest`. `FileCache`'s constructor narrows its `$request` param from `RequestInterface` to `ProcessingRequest` (every real call site — `FileCacheMiddleware`, `CacheStalenessMiddleware`, `ResponseCacher` — already only ever passes a `ProcessingRequest`). `FileCache` then: checks `$request->cacheHash()`; if null, computes via the injected `RequestHasher` and calls `setCacheHash()`; reuses the memoized value for both the body and meta paths, so the hash is computed at most once per request lifecycle no matter how many `FileCache`/`CacheStalenessMiddleware` consumers need it.

### `CacheStalenessMiddleware` does not get its own `request_hasher`

`CacheStalenessMiddleware` only ever proceeds past its `hasResponse()` guard when `FileCacheMiddleware` already ran and found a cache hit (documented pairing contract in `CacheStalenessMiddleware.php`) — and the only way `FileCacheMiddleware` sets that response is via its own `FileCache` construction, which memoizes `cacheHash()` on the `ProcessingRequest` first. So by the time `CacheStalenessMiddleware` builds its own `FileCache`, the hash is already guaranteed to be set by whatever `RequestHasher` `FileCacheMiddleware` was configured with. Therefore:

- No `request_hasher` config key on `CacheStalenessMiddleware` — it would be silently ignored whenever correctly paired, and only relevant in an already-misconfigured pipeline.
- `FileCache`'s fallback to a default `QueryRequestHasher` (when `cacheHash()` isn't already memoized) only matters in that misconfigured case, and stays a silent fallback with no extra logging/guard — deploys wipe the cache directory anyway, so there's no risk of a stale/mismatched hash carrying over between deploys.

### Backward compatibility

`CacheFilePath` and `FileCache` are internal abstractions, not documented extension points (`docs/agents/architecture.md` lists them as internal helpers, unlike middlewares/matchers/handlers) — so narrowing `FileCache`'s constructor type (`RequestInterface` → `ProcessingRequest`) and changing `CacheFilePath::path()`'s third parameter's meaning (raw query → precomputed hash) are not breaking changes for any documented usage. The `request_hasher` config key/constructor param on `FileCacheMiddleware` and `DefaultProxyRequestHandler` is purely additive (optional, appended last, defaults preserve existing construction).

`QueryRequestHasher`'s output does **not** need to byte-match today's inline `hash('sha256', $query)` computation — cache directories are always wiped on deploy, so there's no expectation of preserving pre-upgrade cache entries across a deploy that introduces this feature.

### Scope boundaries

This issue is about the hash/filename generation only — `RequestHasher` decides *what string identifies a request's cache entry*, nothing more. Deciding whether a request/response is cacheable at all is already handled separately (via matchers on `FileCacheMiddleware`, e.g. `StatusCodeMatcher`, `RequestMethodMatcher`) and is out of scope here.

### Edge cases

Producing a filesystem-safe, collision-appropriate string is the implementing developer's responsibility, not Tent's. `RequestHasher::hash()` is trusted as-is — no sanitization, length checks, or validation is performed on its return value. If a custom hasher returns something unsafe (invalid filesystem characters, empty string, etc.), the resulting file operation fails/errors naturally; Tent does not guard against it.

### Security

Same principle as edge cases: safety of what goes into the hash is the developer's responsibility. If a custom hasher folds in sensitive request data (e.g. headers/cookies to key private/authenticated responses separately), it must hash that data (e.g. SHA-256) rather than embedding it in the cache key/filename directly. A static, non-sensitive prefix is fine to combine with a hash for readability/namespacing — e.g. `"private_" . hash('sha256', $sensitiveData)` — but the sensitive part itself must always be hashed. This guidance belongs in `docs/HOW_TO_USE_DARTHJEE-TENT.md`, in a section dedicated to writing custom `RequestHasher` implementations.

### Testing strategy

Following the project's existing per-method test-file convention (e.g. `FileCacheMiddleware/FileCacheMiddleware{Build,ProcessRequest,ProcessResponse,Symmetry}Test.php`):

- `QueryRequestHasher` — new test verifying `hash()` on a request produces the expected digest, and `build([])` returns an instance regardless of params.
- `CacheFilePathTest.php` (existing) — update for the new signature: now takes a precomputed hash string directly rather than a raw query.
- `ProcessingRequest` — new test alongside the existing `SetHeader`/`SetRequestPath` ones, covering `cacheHash()`/`setCacheHash()`: starts `null`, set-then-get returns the same value.
- `FileCache` tests — extend with the core contract of this feature:
  - no hasher injected → falls back to `QueryRequestHasher`.
  - custom hasher injected → its output drives both body and meta file paths.
  - the hasher is invoked at most once even though both paths are derived from it (mock/spy call-count assertion).
  - when `$request->cacheHash()` is already set before `FileCache` is constructed, the injected hasher is never called — this is what proves the `CacheStalenessMiddleware` reuse story, testable directly at the `FileCache` level without spinning up the full middleware pipeline.
- `FileCacheMiddlewareBuildTest.php` — extend: `request_hasher` key builds and wires the configured class; omitting it defaults to `QueryRequestHasher`.
- `DefaultProxyRequestHandler` build test — extend similarly, confirming `request_hasher` passes through into the internally-constructed `FileCacheMiddleware`.
- `FileCacheMiddlewareSymmetryTest.php` — extend with a custom-hasher variant: write via `processResponse()`, then a fresh request/middleware pair with the same custom hasher reads it back as a cache hit via `processRequest()` — proves the round trip works end-to-end, not just that methods get called.
- `CacheStalenessMiddlewareProcessRequestTest.php` (existing) — add a case where `FileCacheMiddleware` (with a non-default hasher) runs first, then `CacheStalenessMiddleware` locates the same cache entry with no hasher configured on it at all — the integration-level proof that it correctly relies on the memoized hash on `ProcessingRequest`.

No test is planned for "hasher returns an unsafe/garbage string" — per the edge cases/security stance above, that's explicitly not Tent's concern.

## Benefits

- Enables caching of private/authenticated requests safely, by letting the cache key depend on headers instead of only the query string.
- Extensible without touching Tent's internals — developers plug in their own `RequestHasher` implementation via configuration, following the same pattern already used for matchers.
- Fully backward compatible — existing configurations keep working unchanged, defaulting to the current query-based hashing behavior.
