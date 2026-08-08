# Issue: add a header filter configuration for FileCacheMiddleware

## Description

`FileCacheMiddleware` (`source/source/lib/middlewares/FileCacheMiddleware.php`) supports `skip_cache_header`: when configured, and the named header is found in either the request or the response, cache reads and writes are bypassed for that request lifecycle. This is wired through `DefaultProxyRequestHandler` (`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`) as well, and documented in `docs/guides/tent/cache-configuration.md` and `docs/guides/tent/request-handlers.md`.

## Problem

There's no way to do the inverse of `skip_cache_header`: require a header's presence in the **response** before a response is allowed to be cached at all. Today, caching is gated only by the configured matchers (e.g. status code) — there's no backend-driven "this response is safe to persist" signal.

## Expected Behavior

- A new `require_cache_header` configuration option (string header name) is checked **only** on the response — never the request. Presence of the header elsewhere (e.g. only in the request) does not satisfy the requirement.
- When `require_cache_header` is not configured, behavior is unchanged (no-op).
- When configured and the header is found in the response, the response remains cacheable (subject to the existing matchers, e.g. status code).
- When configured and the header is **missing** from the response, the response is not cached, regardless of matchers.
- Cache **reads** are unaffected by `require_cache_header` — it only gates writes. A response that was never written to cache (because it lacked the header) simply can't be found on a later read either, so no separate read-side check is needed.
- When both `skip_cache_header` and `require_cache_header` are configured and both headers are found in the response, the response is not cached — `skip_cache_header` wins regardless of whether `require_cache_header`'s condition was also satisfied.
- Header matching is case-insensitive, consistent with the existing `skip_cache_header` behavior.

## Solution

Add a new `require_cache_header` configuration option, the counterpart to `skip_cache_header`:

```php
'skip_cache_header'    => 'X-Skip-Cache',   // presence in request OR response → don't cache
'require_cache_header' => 'X-Cache-Allow',  // absence in response → don't cache (response-only)
```

### Scope: response-only, not request

`require_cache_header` is checked **only** on the response, unlike `skip_cache_header` which checks both request and response:

- **Semantics**: `require_cache_header` is a backend-asserted allowlist signal for "this response is safe to cache" — only the upstream response can meaningfully carry that assertion. `skip_cache_header` is an opt-out signal either side can raise.
- **Security**: honoring this header on the *request* side would let a client force writes to our disk cache simply by sending an arbitrary header, with no upstream involvement. Restricting the check to the response means only the (presumably trusted) backend controls whether a response gets persisted to disk.

### Combined behavior with `skip_cache_header`

`require_cache_header` only gates the **write** path (`FileCacheMiddleware::processResponse`). Following the existing semantic-method style (`shouldSkipCache`, `shouldSkipCacheForResponse`), the conditions are extracted into one private method rather than inlined as a compound boolean:

```php
public function processResponse(Response $response): Response
{
    if ($this->shouldSkipCacheWrite($response)) {
        return $response;
    }

    if ($this->isCacheable($response)) {
        $cache = new FileCache($response->request(), $this->location, $this->requestHasher);
        (new ResponseCacher($cache, $response))->process();
    }
    return $response;
}

private function shouldSkipCacheWrite(Response $response): bool
{
    return $this->shouldSkipCacheForResponse($response)
        || $this->shouldSkipCache($response->request())
        || !$this->meetsRequireCacheHeader($response);
}
```

- `meetsRequireCacheHeader($response)` returns `true` when `require_cache_header` is not configured (null), or when the configured header name is found among the response headers.
- This naturally yields the rule: when both `skip_cache_header` and `require_cache_header` are configured and both headers are found in the response, the response is not cached — `skip_cache_header`'s check already short-circuits to "don't cache" independent of `require_cache_header`'s own outcome. No special-case interaction logic is needed.
- The **read path** (`FileCacheMiddleware::processRequest`) is untouched.

### Wiring

- `FileCacheMiddleware::__construct` / `::build()` — new `?string $requireCacheHeader = null` param / `require_cache_header` attribute, appended after the existing params (`$requestHasher`) to avoid breaking existing positional constructor calls in tests.
- `DefaultProxyRequestHandler::__construct` / `::build()` — same, appended after `$filterQueryParams`, threaded through to the `FileCacheMiddleware` it constructs in `initializeMiddlewares()`.

### Docs

- `docs/guides/tent/cache-configuration.md` — new section (e.g. "Require header before caching") added after "Bypass cache with request header", mirroring that section's style and showing `require_cache_header` alongside `skip_cache_header` in the manual `FileCacheMiddleware` example too.
- `docs/guides/tent/request-handlers.md` — new row in the `DefaultProxyRequestHandler` options table, next to the existing `skip_cache_header` row.
- `docs/guides/tent/middlewares.md` — left as-is; it doesn't document `skip_cache_header` either, so no precedent to extend there.

### Testing strategy

Mirror the existing `skip_cache_header` test coverage in `FileCacheMiddlewareProcessResponseTest.php`, `FileCacheMiddlewareBuildTest.php`, and the `DefaultProxyRequestHandler` test suite, adding cases for `require_cache_header`:

- Not configured → current behavior unchanged (nothing gated by it).
- Configured and header present in response → response is cached (given the other matchers/conditions pass).
- Configured and header missing from response → response is **not** cached.
- Case-insensitive header name matching (matching `shouldSkipCacheForResponse`'s existing case-insensitive behavior).
- Header present only in the **request**, not the response → response is still **not** cached (confirms request-side presence is ignored, per the response-only scope decision).
- Both `skip_cache_header` and `require_cache_header` configured, both headers found in the response → not cached (the issue's explicit combined rule).
- Both configured, only `require_cache_header`'s header found (skip header absent) → cached.
- `DefaultProxyRequestHandler::build()` wiring — `require_cache_header` param is threaded through to the constructed `FileCacheMiddleware`, mirroring the existing `skip_cache_header` wiring test.

### Backward compatibility

Fully additive, no breaking changes: `require_cache_header` is a new nullable param defaulting to `null`, appended at the end of both constructors, and `meetsRequireCacheHeader()` returns `true` (no-op) when unconfigured. Existing configs, positional constructor calls, and cached behavior are unaffected.

## Benefits

- Gives backends a way to actively opt responses into caching, instead of only having matcher-based (status code) filtering.
- Keeps the trust boundary correct: only the upstream backend — not the client — can decide whether a response is persisted to disk.
- Symmetric, discoverable API alongside `skip_cache_header`, with no breaking changes to existing configurations.
