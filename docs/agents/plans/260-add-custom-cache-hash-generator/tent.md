# tent Plan: Add custom cache hash generator

Main plan: [plan.md](plan.md)

## Shared contracts

- Must produce: `Tent\Cache\RequestHasher` interface, `Tent\Cache\QueryRequestHasher` default implementation, the `request_hasher` config key on `FileCacheMiddleware`/`DefaultProxyRequestHandler`, and `ProcessingRequest::cacheHash()`/`setCacheHash()`. See [plan.md](plan.md)'s "Shared contracts" for exact shapes.
- Must NOT add a `request_hasher` key to `CacheStalenessMiddleware` — it relies on the hash already memoized on `ProcessingRequest`.
- `product-dev-extending` depends on these exact class/namespace/method names and the `request_hasher` config shape when writing the new docs guide — do not rename anything here without updating that plan too.

## Implementation Steps

### Step 1 — Add the `RequestHasher` interface and default `QueryRequestHasher`

Create `source/source/lib/cache/RequestHasher.php`:

```php
namespace Tent\Cache;

use Tent\Models\RequestInterface;

interface RequestHasher
{
    public function hash(RequestInterface $request): string;
    public static function build(array $params): self;
}
```

Create `source/source/lib/cache/QueryRequestHasher.php`:

```php
namespace Tent\Cache;

use Tent\Models\RequestInterface;

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

Register both in `source/source/loader.php` right after `require_once __DIR__ . '/lib/models/RequestInterface.php';` (line 9) — both only depend on `RequestInterface`, which is already loaded there, and this puts them well before every consumer (`FileCacheMiddleware`, `FileCache`, `DefaultProxyRequestHandler`).

### Step 2 — Memoize the hash on `ProcessingRequest`

In `source/source/lib/models/ProcessingRequest.php`, add a `cacheHash` property (not in the constructor's `ATTRIBUTES` allow-list — it's always computed, never seeded) and two methods, mirroring the existing `response()`/`setResponse()` pair:

```php
public function cacheHash(): ?string
{
    return $this->cacheHash;
}

public function setCacheHash(string $hash): string
{
    return $this->cacheHash = $hash;
}
```

### Step 3 — Change `CacheFilePath::path()` to take a precomputed hash

In `source/source/lib/utils/CacheFilePath.php`, rename the third parameter from `$query` to `$hash` and drop the internal `hash('sha256', ...)` call — the caller now passes the already-computed digest:

```php
public static function path(string $type, string $basePath, string $hash): string
{
    switch ($type) {
        case 'body':
            return $basePath . '/' . $hash . '.body.dat';
        case 'meta':
            return $basePath . '/' . $hash . '.meta.json';
        default:
            throw new InvalidArgumentException("Invalid cache type: $type");
    }
}
```

Update the docblock accordingly.

### Step 4 — Wire the hasher into `FileCache`

In `source/source/lib/content/FileCache.php`:
- Narrow the constructor's `$request` parameter type from `RequestInterface` to `ProcessingRequest` (add `use Tent\Models\ProcessingRequest;`, drop the now-unused `RequestInterface` import if nothing else in the file needs it — check first).
- Add a `?RequestHasher $requestHasher = null` constructor parameter (`use Tent\Cache\RequestHasher;`, `use Tent\Cache\QueryRequestHasher;`).
- In the constructor: if `$request->cacheHash()` is `null`, compute it via `($requestHasher ?? new QueryRequestHasher())->hash($request)` and store it via `$request->setCacheHash(...)`; otherwise reuse the already-memoized value. Use this single hash value for both `$this->bodyFilePath` and `$this->metaFilePath` (replacing the current two separate `CacheFilePath::path(...)` calls that each derive their own hash from `$this->request->query()`).
- Update `fullPath(string $type)` similarly to use the memoized hash instead of `$this->request->query()`.

### Step 5 — Add `request_hasher` config to `FileCacheMiddleware`

In `source/source/lib/middlewares/FileCacheMiddleware.php`:
- Add `use Tent\Cache\RequestHasher;` and `use Tent\Cache\QueryRequestHasher;`.
- Add a `private RequestHasher $requestHasher;` property.
- Constructor: append `?RequestHasher $requestHasher = null` as the last parameter; store `$requestHasher ?? new QueryRequestHasher()`.
- `build()`: read `$attributes['request_hasher']` — when present, build it via `$attributes['request_hasher']['class']::build($attributes['request_hasher'])`; otherwise pass `null` through to the constructor.
- Pass `$this->requestHasher` into both `new FileCache($request, $this->location, ...)` construction sites (`processRequest()` and `processResponse()`).
- Update the class docblock's usage example to mention `request_hasher` (brief, one example is enough — full docs live in `product-dev-extending`'s work).

### Step 6 — Add `request_hasher` config to `DefaultProxyRequestHandler`

In `source/source/lib/request_handlers/DefaultProxyRequestHandler.php`:
- Add `use Tent\Cache\RequestHasher;`.
- Add a `private ?RequestHasher $requestHasher;` property.
- Constructor: append `?RequestHasher $requestHasher = null` as the last parameter; store it as-is (leave resolving the `QueryRequestHasher` default to `FileCacheMiddleware` itself, so there's a single source of truth for the default).
- `build()`: read `$params['request_hasher']` the same way as Step 5, pass through to the constructor.
- `initializeMiddlewares()`: pass `$this->requestHasher` as the new last argument to the `FileCacheMiddleware` it constructs.

### Step 7 — Tests

Add/extend PHPUnit tests under `source/tests/unit/lib/`, following the existing per-class/per-method file convention:

- New `source/tests/unit/lib/cache/QueryRequestHasher/QueryRequestHasherHashTest.php` — `hash()` returns `hash('sha256', $request->query())` for a given `ProcessingRequest`/`Request` fixture.
- New `source/tests/unit/lib/cache/QueryRequestHasher/QueryRequestHasherBuildTest.php` — `build([])` (and with arbitrary extra keys) returns a `QueryRequestHasher` instance.
- Update `source/tests/unit/lib/utils/CacheFilePathTest.php` for the new `$hash`-based signature (pass a literal hash string instead of a query string; assert the same path shape).
- New `source/tests/unit/lib/models/ProcessingRequest/ProcessingRequestCacheHashTest.php` — `cacheHash()` starts `null`; `setCacheHash()` sets and returns the value; subsequent `cacheHash()` calls return the same memoized value.
- Extend `source/tests/unit/lib/models/FileCache/` tests (or add a new `FileCacheRequestHasherTest.php`):
  - No hasher injected → falls back to `QueryRequestHasher`'s output.
  - Custom (mock) hasher injected → its return value drives both `bodyFilePath`/`metaFilePath` (assert via `exists()`/`store()` producing files named from that hash).
  - The injected hasher's `hash()` is called **at most once** per `FileCache` construction (mock with call-count assertion), even though both body and meta paths are derived from it.
  - When `$request->cacheHash()` is already set before constructing `FileCache`, the injected hasher is **never called** (mock never invoked) — proves the `CacheStalenessMiddleware` reuse story at the unit level.
- Extend `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` — `request_hasher` key builds and wires the configured class; omitting it defaults to `QueryRequestHasher`.
- Extend `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — `request_hasher` passes through into the internally-constructed `FileCacheMiddleware` (assert via reflection or an observable side effect, matching however existing tests already verify `DefaultProxyRequestHandler`'s internal middleware wiring).
- Extend `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareSymmetryTest.php` with a custom-hasher variant: `processResponse()` writes via a custom hasher, a fresh middleware/request pair configured with the same hasher class reads it back as a cache hit via `processRequest()`.
- Extend `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — `FileCacheMiddleware` configured with a non-default hasher runs first and sets the response/memoized hash; `CacheStalenessMiddleware`, configured with no hasher at all, still locates the same cache entry.

No test is planned for a hasher returning an unsafe/garbage string — per the issue's edge-cases/security section, that's explicitly not Tent's concern.

## Files to Change

- `source/source/lib/cache/RequestHasher.php` — new interface.
- `source/source/lib/cache/QueryRequestHasher.php` — new default implementation.
- `source/source/loader.php` — register the two new files.
- `source/source/lib/models/ProcessingRequest.php` — add `cacheHash()`/`setCacheHash()`.
- `source/source/lib/utils/CacheFilePath.php` — change third parameter from raw query to precomputed hash.
- `source/source/lib/content/FileCache.php` — narrow `$request` type to `ProcessingRequest`, accept/use a `RequestHasher`, memoize via `cacheHash()`.
- `source/source/lib/middlewares/FileCacheMiddleware.php` — add `request_hasher` constructor param/config key, pass it into `FileCache`.
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — add `request_hasher` constructor param/config key, pass it through to `FileCacheMiddleware`.
- `source/tests/unit/lib/cache/QueryRequestHasher/QueryRequestHasherHashTest.php` — new.
- `source/tests/unit/lib/cache/QueryRequestHasher/QueryRequestHasherBuildTest.php` — new.
- `source/tests/unit/lib/utils/CacheFilePathTest.php` — updated for new signature.
- `source/tests/unit/lib/models/ProcessingRequest/ProcessingRequestCacheHashTest.php` — new.
- `source/tests/unit/lib/models/FileCache/` — extended/new test file for hasher wiring and memoization.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` — extended.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareSymmetryTest.php` — extended.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — extended.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — extended.

## CI Checks

- `source`: `composer coverage` (CI job: `unit_test`)
- `source`: `composer lint` (CI job: `checks`)

## Notes

- Check whether `FileCache.php` still needs the `RequestInterface` import after narrowing to `ProcessingRequest` — `ProcessingRequest implements RequestInterface`, so it may become unused; remove it if so to keep `composer lint` (phpmd unused-code check) happy.
- `DefaultProxyRequestHandler`'s default `RequestHasher` resolution is intentionally left to `FileCacheMiddleware` (constructed with `null` when not configured) rather than duplicating the `QueryRequestHasher` default in two places.
