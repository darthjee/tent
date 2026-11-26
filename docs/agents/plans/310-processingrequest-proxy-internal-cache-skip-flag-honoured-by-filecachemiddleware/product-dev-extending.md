# Product-dev-extending Plan: ProcessingRequest: proxy-internal cache-skip flag honoured by FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

- Rely on `RequestInterface::skipCache(): void` / `cacheSkipped(): bool` (produced by tent): one-way, proxy-internal, never a header, not settable from configuration.
- With the flag set: no cache read, no write, no `evictOn` eviction, no staleness refresh. `CacheCleanupMiddleware` ignores it.
- Placement: full bypass only from `prependMiddlewares`; from `middlewares` only the write is bypassed on `default_proxy`.

## Implementation Steps

### Step 1 — Cache guide and middleware-authoring guide
- `docs/guides/tent/cache-configuration.md`: add a section after "Bypass cache with request header" — e.g. "Bypass cache from a middleware" — with a short custom-middleware example calling `$request->skipCache()`, a `prependMiddlewares` rule example, what the flag does (no read, write, eviction or refresh; never forwarded as a header), the placement rule (in `middlewares` only the write is skipped), and the load-amplification warning: "every flagged request goes upstream; if your trigger is client-controllable, so is the bypass."
- `docs/creating-middlewares.md`: in the `FileCacheMiddleware` built-in section mention `skipCache()` next to `skip_cache_header`; in "Best Practices → Order of Middleware Execution" state that cache guards belong in `prependMiddlewares`, linking to the cache guide section.

### Step 2 — Fix the stale invalidation note
- `docs/file-cache-middleware-matchers.md` (line ~259): replace "There is currently no built-in cache expiry or invalidation mechanism" with an accurate note pointing to `evictOn` (status-based eviction, #306), `CacheStalenessMiddleware` (refresh) and `CacheCleanupMiddleware` (invalidation on mutating methods), keeping the "delete files in `location`" manual-clear hint.

## Files to Change
- `docs/guides/tent/cache-configuration.md` — new "Bypass cache from a middleware" section.
- `docs/creating-middlewares.md` — `skipCache()` mention and placement guidance.
- `docs/file-cache-middleware-matchers.md` — correct the stale invalidation note.

## Notes
- Docs go in their own commit(s), separate from code and tests.
- Do not describe a configuration key or header for the flag — there is none by design.
- Mention darthjee/tent#311 nowhere in user docs; it is an internal defect, not a documented limitation.
