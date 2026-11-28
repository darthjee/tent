# Issue: CacheStalenessMiddleware never runs on a cache hit: the request-middleware chain stops at FileCacheMiddleware

## Background

`CacheStalenessMiddleware` serves stale cache hits and schedules a background refresh (and, since #306, an optional `evictOn` eviction) through `BackgroundRefresher`. Its `processRequest()` acts only when `$request->hasResponse()` is true, and the docs (`docs/guides/tent/middlewares.md`, class docblock) say to configure it **after** `FileCacheMiddleware` with the same `location`.

## Problem

`RequestHandler::applyRequestMiddlewares()` stops the request-middleware chain at the first middleware whose result reports `hasResponse()`:

```php
foreach ($this->middlewares as $middleware) {
    $modifiedRequest = $middleware->processRequest($modifiedRequest);
    if ($modifiedRequest->hasResponse()) {
        return $modifiedRequest;
    }
}
```

On a cache hit, `FileCacheMiddleware::processRequest()` calls `setResponse()`, so the chain returns before `CacheStalenessMiddleware` is reached. On a miss, `hasResponse()` is false and `CacheStalenessMiddleware` returns immediately. In either case, no refresh is ever scheduled when the middleware is configured in a rule — whatever slot it is placed in (`prependMiddlewares` puts it before the cache, where `hasResponse()` is always false).

Consequences, if confirmed:

- Stale-while-revalidate never happens in production: stale entries are served forever (until removed by other means).
- #306's refresh-time `evictOn` on `CacheStalenessMiddleware` is unreachable.

The existing tests (`source/tests/unit/lib/middlewares/CacheStalenessMiddleware/`) call `processRequest()` directly on a request with a pre-set response; no test drives the middleware through a `RequestHandler`, which is why this went unnoticed.

## Expected

A `CacheStalenessMiddleware` configured with `FileCacheMiddleware` (same `location`) in a rule evaluates every response that `FileCacheMiddleware` served from cache, and schedules a background refresh when the entry is older than `maxAgeSeconds` — using `FileCacheMiddleware`'s header filter for the refresh write.

## Solution

**Chosen approach: response-phase staleness check driven by a cache-hit marker.** The request-middleware chain and the `Middleware` API stay unchanged.

1. **`CacheHit` marker on `ProcessingRequest`** (not on `RequestInterface`): a small value object holding the `FileCache` entry that was served and the `HeaderFilter` of the `FileCacheMiddleware` that served it. Exposed as `ProcessingRequest::setCacheHit(CacheHit)` / `cacheHit(): ?CacheHit`.
2. **`FileCacheMiddleware::processRequest()`** sets the marker alongside `setResponse()` on a cache hit (and never on a miss, an eviction-on-read, or a skipped cache).
3. **`CacheStalenessMiddleware` moves its check to `processResponse()`**, which `RequestHandler::applyResponseMiddlewares()` already invokes for every middleware, cache hit included. It acts only when `$response->request()` is a `ProcessingRequest` carrying a `CacheHit` and the request is not `cacheSkipped()`; it reads the entry's timestamp from `$hit->cache()` (same memoized hash) and, when older than `maxAgeSeconds`, schedules the `BackgroundRefresher` with `$hit->headerFilter()`. Its `processRequest()` becomes a no-op (inherited default). The response is never modified.
4. Debounce sentinel, scheduler, `host` and refresh-time `evictOn` behavior are unchanged.

Why this one over the alternatives considered:

- *Opt-in request hook* (`Middleware::runsAfterResponse()`): changes the `Middleware` API and the handler loop, yet still needs the marker for provenance and the shared filter.
- *Merge staleness into `FileCacheMiddleware`*: provenance and filter come for free, but `CacheStalenessMiddleware` would still need one of the other mechanisms to keep existing configs working — the largest change.
- *Response phase without a marker*: cannot tell a cache-served response from any other, violating provenance.

Placement note: since the check now runs in the response phase, `CacheStalenessMiddleware` works wherever it sits relative to `FileCacheMiddleware` in the same handler; the docs keep recommending "after `FileCacheMiddleware`, same `location`" for readability.

## Scope

**In scope**

- Make `CacheStalenessMiddleware` actually act on a cache hit, via the response-phase check and `CacheHit` marker (see Solution).
- Provenance: staleness is evaluated only for responses served by `FileCacheMiddleware`.
- Header-filter alignment: the `BackgroundRefresher` write uses the same header filter as `FileCacheMiddleware`; `CacheStalenessMiddleware`'s own `mode`/`excluded_headers`/`additional_excluded_headers`/`allowed_headers` options are deprecated (see Backward compatibility).
- The request-phase short-circuit stays unchanged for every other middleware.
- Handler-level tests for both `proxy` and `default_proxy`.
- Docs (`docs/guides/tent/middlewares.md`, class docblock) updated to describe the working behavior.

**Out of scope**

- The debounce sentinel never expiring (#331).
- Multiple cache/staleness pairs with different `location`s in one rule.
- Config-load-time validation of middleware ordering (not needed: the response-phase check works in any position within the handler).
- Changes to the refresh scheduler (`fastcgi_finish_request` vs. synchronous fallback, real job queue).
- Behavior changes to #306's refresh-time `evictOn`; it only becomes reachable.
- A first-class `default_proxy` option for stale-while-revalidate; users keep adding `CacheStalenessMiddleware` to the rule's `middlewares`.
- New observable signals (e.g. an `X-Cache-Stale` header); the existing `Logger::debug` lines remain the only signal.

## Edge cases

- **Ordering**: because the check runs in the response phase, which visits every middleware of the handler, `CacheStalenessMiddleware` works whether it is placed after `FileCacheMiddleware`, above it, or in `prependMiddlewares`. Docs keep recommending "after, same `location`"; a handler-level test covers the prepended position.
- **`default_proxy`**: `FileCacheMiddleware` is added during construction, so a `CacheStalenessMiddleware` in the rule's `middlewares` lands after it. This is the main production path and gets its own handler-level test.
- **Short-circuit preserved for everyone else**: after a cache hit, request middlewares after `FileCacheMiddleware` (e.g. `SetHeadersMiddleware`, custom request middlewares) are still skipped in the request phase — the request chain is not modified. This is an acceptance criterion with a regression test.
- **Eviction on read**: when `FileCacheMiddleware`'s `evictOn` removes the entry, the request becomes a miss, so there is no response and no refresh. This is already correct; it gets a test.
- **`cacheSkipped()`** (#310): already checked first by `CacheStalenessMiddleware`; no change.
- **Provenance — a response not served from the cache**: if another middleware sets a response while a cache entry for the same request exists on disk, the staleness check must not act on it. The fix must only evaluate staleness for responses `FileCacheMiddleware` actually served (e.g. a served-from-cache marker on the request, or by merging staleness into `FileCacheMiddleware`). This constrains the choice of solution.
- **Custom request hasher**: no mismatch — `FileCache` memoizes the hash on the request (`$request->cacheHash()`), so once `FileCacheMiddleware` has served the hit (guaranteed by provenance) the staleness `FileCache` reuses the hash computed with the configured `request_hasher`. Covered by a regression test with a custom hasher through the fixed chain.
- **Debounce sentinel never expires**: out of scope here; tracked in #331.
- **Multiple cache/staleness pairs with different `location`s in one rule**: out of scope (not a realistic configuration).

## Backward compatibility

The project follows semver (currently 1.3.0); this fix ships in a minor release, so nothing user-facing is removed.

- **Behavior change (accepted)**: rules that already configure `CacheStalenessMiddleware` start refreshing for the first time — more upstream traffic, and synchronous latency on mod_php. Called out in the release note.
- **Header-filter options on `CacheStalenessMiddleware` (deprecated)**: `mode`, `excluded_headers`, `additional_excluded_headers`, `allowed_headers` are still accepted by `build()` but ignored — the refresh write uses `FileCacheMiddleware`'s filter. A deprecation warning is logged when any of them is present. Removal is deferred to the next major.
- **Constructor signature (kept)**: the 10-argument positional constructor is unchanged; the header-filter arguments become unused and are marked `@deprecated` in the docblock. Cleanup in the next major.
- **Documented contract (kept)**: existing configs (`CacheStalenessMiddleware` after `FileCacheMiddleware`, same `location`) keep working unchanged.
- **Custom middlewares**: `Middleware` and `RequestHandler` are not changed; request-phase short-circuit semantics stay exactly as today.
- **`CacheHit` marker**: lives on `ProcessingRequest` only — `RequestInterface` is not changed, so third-party implementations are not broken.
- **On-disk format**: cache body/meta files and the `.refreshing` sentinel are unchanged; existing cache directories remain valid.

## Performance & security

**Performance**

- **Synchronous refresh on the shipped image (accepted, documented)**: the production image is `php:8.4-apache` (mod_php), where `fastcgi_finish_request()` does not exist, so `defaultScheduler()` runs the refresh synchronously. Once this fix lands, a non-debounced stale hit pays a full upstream round trip on the default deployment (today it pays nothing because refresh never runs). This is a known consequence, documented in the guide and docblock; true background execution under Apache is out of scope.
- **Extra meta read per hit**: the staleness check re-reads the meta file after `FileCacheMiddleware` already did. Negligible; reuse it if the chosen design makes it free, not required.
- **Debounce race**: `file_exists()` then write of the `.refreshing` sentinel is not atomic, so two concurrent stale hits may both schedule a refresh. Accepted (best-effort debounce); bounded to one extra upstream call per race.
- **Upstream load**: bounded per entry by `maxAgeSeconds` plus the debounce; unchanged.

**Security**

- **Header-filter drift (in scope)**: today the refresh write uses `CacheStalenessMiddleware`'s own header-filter config, only documented to match `FileCacheMiddleware`'s. Once refresh is reachable, a mismatch could store headers the main cache strips (e.g. `Set-Cookie`) and replay them to every client. The refresh write must use the same header filter as `FileCacheMiddleware`.
- **Client headers on refresh**: `BackgroundRefresher` replays the triggering client's request headers upstream and caches the result — same exposure as the original cache write; pre-existing, out of scope.
- **Path safety**: the staleness `FileCache` and sentinel write go through the hardened path guards (#322/#324) and `FileUtils::writeAtomically()`; nothing to do.

## Docs

- `docs/guides/tent/middlewares.md` §`CacheStalenessMiddleware`: describe the working stale-while-revalidate behavior; mark the header-filter options as deprecated (the refresh write shares `FileCacheMiddleware`'s filter); add the mod_php synchronous-refresh caveat, stated explicitly for the shipped `php:8.4-apache` image.
- `CacheStalenessMiddleware` class docblock: mirror the guide, including the caveat.
- `docs/creating-middlewares.md` `CacheStalenessMiddleware` entry: align with the guide. §4 *Short-Circuit the Request Chain* is unchanged (the request chain is not modified).
- `docs/guides/tent/cache-configuration.md` (~line 213) and `docs/request-handlers.md` (~line 226): replace "configure the same header-filtering options" with "shared automatically"; recheck the matching-`evictOn` advice.
- `docs/agents/architecture.md` (~line 88): reword "must run after `FileCacheMiddleware`" to "pairs with `FileCacheMiddleware` (same `location`); acts in the response phase on cache hits".
- `docs/guides/tent/creating-request-hashers.md` (~lines 130, 190): memoized-hash reuse remains correct; reword only if needed.
- Release note / CHANGELOG entry: stale-while-revalidate never actually ran before and now does; on mod_php a stale hit now waits for a synchronous upstream refresh; the staleness middleware's own header-filter options are deprecated (ignored, warning logged) and will be removed in the next major.

## Testing

- A handler-level test (e.g. `DefaultProxyRequestHandler` with `CacheStalenessMiddleware` in `middlewares`, a stale entry on disk and an injected scheduler) asserting that the scheduler is invoked on a stale hit — this test should fail on `main` today, confirming the defect.
- The same with a fresh entry: the scheduler is not invoked.
- `default_proxy` with `CacheStalenessMiddleware` in `middlewares`: stale hit schedules a refresh.
- Short-circuit regression: after a cache hit, a request middleware placed after `FileCacheMiddleware` is still not invoked (`processRequest()`).
- Provenance: a response set by a non-cache middleware, with an old cache entry on disk, does not schedule a refresh (no `CacheHit` marker).
- Unit: `FileCacheMiddleware` sets `CacheHit` on a hit only (not on miss, eviction-on-read, or `skipCache()`); `CacheStalenessMiddleware::processResponse()` ignores responses whose request is not a `ProcessingRequest` or has no marker.
- Eviction on read (`FileCacheMiddleware` `evictOn`): no refresh scheduled.
- Custom `request_hasher`: staleness evaluates the same entry `FileCacheMiddleware` served.
- Header filter: the refresh write applies `FileCacheMiddleware`'s filter; deprecated staleness filter options are ignored and log a deprecation warning.

## Context

- Found while refining #310 (proxy-internal cache-skip flag), whose `CacheStalenessMiddleware` guard depends on this middleware actually running.
- Related: #306 (refresh-time `evictOn`).
- Spun off: #331 (the `.refreshing` debounce sentinel never expires; becomes reachable once this issue is fixed).
