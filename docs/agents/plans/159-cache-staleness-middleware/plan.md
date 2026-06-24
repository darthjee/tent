# Plan: Implement Cache Staleness Middleware

Issue: [159-cache-staleness-middleware.md](../issues/159-cache-staleness-middleware.md)

## Overview

Add a new `Tent\Middlewares\CacheStalenessMiddleware` that sits alongside `FileCacheMiddleware` and, on a cache hit, checks how old the cached entry is. If the entry is older than a configurable `maxAgeSeconds` threshold, the cached response is still served immediately, but a background refresh request is triggered against the upstream so that the cache gets repopulated for the next request.

## Context

`FileCacheMiddleware` (`source/source/lib/middlewares/FileCacheMiddleware.php`) already serves cached responses via `Tent\Content\FileCache` (`source/source/lib/content/FileCache.php`), and `FileCache` already records a Unix `timestamp` in its meta JSON file every time a response is stored (`buildMeta()`). This timestamp is reused today only to emit an `X-Cache-Time` response header (`headers()`); there is no concept of "staleness" or automatic refresh — a cached entry, however old, keeps being served until something else removes it (e.g. the recently added `CacheCleanupMiddleware`, `source/source/lib/middlewares/CacheCleanupMiddleware.php`, which clears cache directories reactively on mutating requests).

This issue asks for a complementary, time-based mechanism: instead of relying on an external mutation to invalidate the cache, the proxy itself notices when a GET response cache entry has aged past a threshold, and proactively refreshes it in the background while still answering the current request from the (stale-but-still-valid) cache.

Important constraint: Tent runs under PHP-FPM/Apache with no existing async/job-queue infrastructure (no `pcntl`, no background worker, no queue). "Non-blocking" background refresh must therefore be approximated using PHP's own mechanisms — `register_shutdown_function()` together with `fastcgi_finish_request()` (when available, i.e. under FPM) lets the response flush to the client before the refresh request runs in the same PHP process during shutdown. This must be implemented behind a small seam (e.g. an injectable "background runner") so unit tests can run the refresh synchronously and assert on it, while production wires the deferred behavior.

## Implementation Steps

### Step 1 — Add a `lastModifiedAt()`/age accessor to `FileCache` (or a thin wrapper)

`FileCache` already stores `timestamp` in its meta file and reads it via `readMeta()`. Add a public method, e.g. `FileCache::timestamp(): ?int`, that exposes the raw stored Unix timestamp (returning `null` when the entry has no metadata / does not exist), so middlewares can compute age without duplicating meta-file parsing. Keep `buildMeta()`/`headers()` behavior unchanged.

### Step 2 — Create `BackgroundRefresher` (or similarly named) service

Add `source/source/lib/service/BackgroundRefresher.php` (namespace `Tent\Service`) responsible for issuing the upstream refresh request and re-storing the result into the same `FileCache` entry, without affecting the response already being sent to the client. It should:

- Accept the `RequestInterface`/`ProcessingRequest` needed to re-issue the upstream call (method, path, headers, query) and an `HttpClientInterface` (reuse `CurlHttpClient`, consistent with `ProxyRequestHandler`).
- Perform the request, build a `Response`, and call `(new ResponseCacher($cache, $response))->process()` (reusing the existing `Tent\Service\ResponseCacher` used by `FileCacheMiddleware::processResponse()`) to overwrite the stale cache entry with fresh content/timestamp.
- Be deferrable: provide a `run(): void` method that performs the refresh synchronously, so the calling middleware can choose whether to invoke it immediately (tests) or via `register_shutdown_function()` (production, see Step 4).
- Log via `Logger::debug()`/`Logger::warn()` on refresh start/success/failure, consistent with existing log conventions (`[<status>] - reason` format where applicable).

### Step 3 — Create `CacheStalenessMiddleware`

Add `source/source/lib/middlewares/CacheStalenessMiddleware.php` (namespace `Tent\Middlewares`, extends `Middleware`):

- Constructor takes a `FolderLocation $location` (must match the `FileCacheMiddleware` location for the same rule) and an `int $maxAgeSeconds`.
- `build(array $attributes)`: reads `location` and `maxAgeSeconds` (or `max_age_seconds`, matching the snake_case convention used elsewhere, e.g. `skip_cache_header` in `FileCacheMiddleware`) from configuration.
- `processRequest(ProcessingRequest $request)`:
  1. Skip if request already has a response set (i.e. `FileCacheMiddleware` did not find/set a cache hit — nothing to evaluate). This middleware should be configured **after** `FileCacheMiddleware` in the middleware list so it only fires when a cache hit already populated the response.
  2. Build a `FileCache` for the same request/location and read its `timestamp()` (Step 1).
  3. Compute age = `time() - timestamp`. If age exceeds `maxAgeSeconds`, log the staleness detection (`Logger::debug('[stale] - cache age exceeds threshold — uri: ..., age: ..., maxAgeSeconds: ...')` or similar) and trigger the background refresh (Step 2) — but do **not** alter `$request`'s already-set cached response; the stale response is still served as-is.
  4. Debounce: track an in-progress/just-triggered refresh per cache entry (e.g. a small lock/marker file written by `BackgroundRefresher` before running and removed after, or a "last refresh attempt" timestamp check) so concurrent requests for the same stale resource within a short window don't all trigger redundant upstream calls.
- No `processResponse()` override needed — staleness is purely a read-time concern; `FileCacheMiddleware` continues to own writing fresh responses to cache.

### Step 4 — Wire the "background" / non-blocking execution

Since there is no real async runtime, approximate non-blocking behavior:

- If `function_exists('fastcgi_finish_request')`, call `register_shutdown_function()` to run `BackgroundRefresher::run()` after `fastcgi_finish_request()` has flushed the response — this is the closest equivalent to "background" under PHP-FPM/Apache+mod_proxy_fcgi.
- If `fastcgi_finish_request` is unavailable (e.g. CLI/test/non-FPM SAPI), fall back to running the refresh inline/synchronously — document this explicitly as a known limitation in the class docblock and in `docs/creating-middlewares.md` (or wherever middleware docs live), since it means "background" guarantees only hold under FPM.
- Keep this branching logic isolated (e.g. a small private method or a separate `Scheduler`/`Deferred` helper) so unit tests can inject a fake that just calls `run()` immediately and assert the refresh happened, without needing FPM-specific globals.

### Step 5 — Register the new classes in the loader

Add `require_once` entries for the new files in `source/source/loader.php`, respecting dependency-first ordering: `BackgroundRefresher` (service) before `CacheStalenessMiddleware` (middleware), grouped with their respective domains (`service`, `middlewares`).

### Step 6 — Tests

Follow the existing test layout under `source/tests/unit/lib/middlewares/<MiddlewareName>/` (mirroring `CacheCleanupMiddleware/` and `FileCacheMiddleware/` subfolders, e.g. `CacheStalenessMiddlewareBuildTest.php`, `CacheStalenessMiddlewareProcessRequestTest.php`):

- `build()` parses `location` and `maxAgeSeconds` correctly.
- Fresh cache (age below threshold): response is served unmodified, no refresh triggered.
- Stale cache (age above threshold): response is still served (unchanged), and the injected/faked background mechanism is invoked exactly once with the expected request/cache target.
- No cache hit (no response set on the request): middleware is a no-op.
- Debounce: a second stale request shortly after the first does not trigger a second refresh.
- `BackgroundRefresher`: unit test that it re-fetches via the injected `HttpClientInterface` and re-stores via `ResponseCacher`/`FileCache`, independent of the middleware.
- Use `Configuration::reset()` in `setUp()` and `Logger::setInstance(new NullLoggerInstance())`, per repo conventions.
- Consider one integration test (under `source/tests/integration/`, if such a suite exists for middleware combinations) exercising `FileCacheMiddleware` + `CacheStalenessMiddleware` together against a rule, asserting the stale response is returned and the cache file's timestamp changes after the refresh runs (using the synchronous fallback path so the test doesn't depend on FPM).

### Step 7 — Documentation

- Update `docs/agents/architecture.md` middleware table to add `CacheStalenessMiddleware`.
- Add a configuration example (mirroring the issue's example) to whichever doc file documents middlewares in depth (e.g. `docs/creating-middlewares.md` or the middlewares doc referenced from `architecture.md`), including the caveat about the FPM-dependent non-blocking behavior.

## Files to Change

- `source/source/lib/content/FileCache.php` — add `timestamp()` accessor.
- `source/source/lib/service/BackgroundRefresher.php` — new file, performs the upstream refresh + re-cache.
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — new middleware implementing staleness detection and triggering refresh.
- `source/source/loader.php` — register the two new classes.
- `source/tests/unit/lib/content/FileCache/` — test(s) for the new `timestamp()` accessor (extend existing `FileCache` test folder).
- `source/tests/unit/lib/service/BackgroundRefresherTest.php` (or folder) — new tests.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/` — new test folder (build + processRequest tests).
- `docs/agents/architecture.md` — add middleware to the table.
- A middleware-focused doc (e.g. `docs/creating-middlewares.md`) — add usage example and the async-limitation caveat, if such a file exists; otherwise add the example inline in the new class's docblock only.

## CI Checks

- `source/`: `docker compose run --rm tent_tests composer tests` (unit + integration test suite)
- `source/`: `docker compose run --rm tent_tests composer lint` (PSR-12 via PHP CodeSniffer)

## Notes

- True non-blocking background execution is not available in this stack (no queue/worker, no `pcntl`); the plan approximates it via `fastcgi_finish_request()` + `register_shutdown_function()` under PHP-FPM, with a synchronous fallback elsewhere. This should be called out clearly in code comments and docs so it isn't mistaken for a guaranteed-async mechanism.
- Debounce strategy (Step 3.4) needs a concrete, simple implementation — the lightest option is a sentinel file (e.g. `<cache-entry>.refreshing`) written before the refresh starts and removed when it finishes/fails, checked before triggering a new one. Avoid over-engineering (no need for a full lock manager).
- `CacheStalenessMiddleware` depends on running after `FileCacheMiddleware` in the configured middleware list for a rule — this ordering constraint should be explicit in the class docblock, similar to how `CacheCleanupMiddleware`'s docblock notes it must share the same `location` as `FileCacheMiddleware`.
- Per-resource/per-rule configuration is already naturally supported since middlewares are configured per rule in `Configuration::buildRule()` — no extra mechanism needed beyond the existing `middlewares` array shape.
