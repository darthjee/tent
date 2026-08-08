# tent Plan: add a header filter configuration for FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

- New config key `require_cache_header` (string, header name) — write-only counterpart to `skip_cache_header`.
- `FileCacheMiddleware::__construct` gains `?string $requireCacheHeader = null`, appended **after** `$requestHasher`. `build()` reads `$attributes['require_cache_header'] ?? null`.
- `DefaultProxyRequestHandler::__construct` gains `?string $requireCacheHeader = null`, appended **after** `$filterQueryParams`. `build()` reads `$params['require_cache_header'] ?? null` and threads it through to the `FileCacheMiddleware` it constructs.
- Gates only the write path; case-insensitive; response headers only (never checked against the request).

## Implementation Steps

### Step 1 — Add `require_cache_header` to `FileCacheMiddleware`

In `source/source/lib/middlewares/FileCacheMiddleware.php`:

- Add `private ?string $requireCacheHeader;` property with docblock, mirroring `$skipCacheHeader`.
- Add `?string $requireCacheHeader = null` as the last constructor param; assign it in the constructor body.
- In `build()`, read `$attributes['require_cache_header'] ?? null` and pass it as the new constructor arg.
- Update the class-level `## Usage Example` docblock to mention `require_cache_header` alongside `skip_cache_header`.

### Step 2 — Implement the write-gate logic

Still in `FileCacheMiddleware.php`:

- Add `private function meetsRequireCacheHeader(Response $response): bool` — returns `true` when `$this->requireCacheHeader === null`, otherwise iterates `$response->headers()` the same way `shouldSkipCacheForResponse()` does (lowercased `headerName . ':'` prefix match against each raw header line) and returns whether a match was found.
- Add `private function shouldSkipCacheWrite(Response $response): bool` combining the three write-gate conditions:
  ```php
  private function shouldSkipCacheWrite(Response $response): bool
  {
      return $this->shouldSkipCacheForResponse($response)
          || $this->shouldSkipCache($response->request())
          || !$this->meetsRequireCacheHeader($response);
  }
  ```
- Update `processResponse()` to call `shouldSkipCacheWrite($response)` instead of the current inline `$this->shouldSkipCacheForResponse($response) || $this->shouldSkipCache($response->request())` check.
- Do **not** touch `processRequest()` — the read path stays exactly as-is.

### Step 3 — Wire through `DefaultProxyRequestHandler`

In `source/source/lib/request_handlers/DefaultProxyRequestHandler.php`:

- Add `private ?string $requireCacheHeader;` property with docblock, mirroring `$skipCacheHeader`.
- Add `?string $requireCacheHeader = null` as the last constructor param (after `$filterQueryParams`); assign it in the constructor body.
- In `build()`, read `$params['require_cache_header'] ?? null` and pass it to `new self(...)`.
- In `initializeMiddlewares()`, pass `$this->requireCacheHeader` as the new trailing arg to `new FileCacheMiddleware(...)`.
- Update the class-level docblock's param list and add a `require_cache_header` bullet to the `build()` docblock's params list, mirroring the `skip_cache_header` entries.

### Step 4 — Unit tests

Add cases mirroring the existing `skip_cache_header` coverage:

**`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php`**
- `require_cache_header` attribute is read and passed through when building via `build()`.

**`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php`**
- Not configured → current behavior unchanged.
- Configured, header present in response → response is cached (matchers permitting).
- Configured, header missing from response → response is **not** cached.
- Case-insensitive header name matching.
- Header present only in the **request**, not the response → response is still **not** cached.
- Both `skip_cache_header` and `require_cache_header` configured, both headers found in the response → not cached.
- Both configured, only `require_cache_header`'s header found (skip header absent) → cached.

**`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php`**
- Sanity check: `require_cache_header` configured has no effect on the read path (cache read behaves identically whether or not it's set, given an existing cache entry).

**`source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php`**
- `require_cache_header` param is threaded through `build()` to the constructed `FileCacheMiddleware`, mirroring the existing `skip_cache_header` wiring test.

**`source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerCachedTest.php`**
- End-to-end: a response with/without the configured header is/isn't cached through the full handler stack.

## Files to Change

- `source/source/lib/middlewares/FileCacheMiddleware.php` — new param/property, `meetsRequireCacheHeader()`, `shouldSkipCacheWrite()`, `build()` wiring, docblock update.
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — new param/property, `build()` wiring, `initializeMiddlewares()` wiring, docblock update.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` — new build test case(s).
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` — new write-gate test cases.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php` — read-path no-op sanity case.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — wiring test case.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerCachedTest.php` — end-to-end case(s).

## CI Checks

- `source/`: `docker compose run --rm tent_tests composer tests` (CI job: `unit_test`)
- `source/`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)

## Notes

- No new class is added, so `source/source/loader.php` needs no changes (manual `require_once` loading only applies to new files).
- Keep the new param strictly at the **end** of both constructors' signatures — existing tests construct these classes positionally (e.g. `new DefaultProxyRequestHandler($this->baseUrl, false, ['2xx'], $this->httpClient)`), and appending preserves those call sites unchanged.
