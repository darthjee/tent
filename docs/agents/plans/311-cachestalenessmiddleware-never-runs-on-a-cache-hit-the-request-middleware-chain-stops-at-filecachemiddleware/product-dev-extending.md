# Product-dev-extending Plan: CacheStalenessMiddleware never runs on a cache hit: the request-middleware chain stops at FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

Relies on the `CacheStalenessMiddleware` contract in [plan.md § Shared contracts](plan.md#shared-contracts): response-phase check on cache hits marked by `FileCacheMiddleware`, refresh write uses `FileCacheMiddleware`'s header filter, header-filter options on the staleness middleware deprecated (accepted, ignored, warning logged), placement flexible but "after `FileCacheMiddleware`, same `location`" still recommended, synchronous refresh on mod_php.

## Implementation Steps

### Step 1 — Update the middleware guides

- `docs/guides/tent/middlewares.md` §`CacheStalenessMiddleware`: describe the working stale-while-revalidate behavior (acts on responses served by `FileCacheMiddleware`); mark `mode`/`excluded_headers`/`additional_excluded_headers`/`allowed_headers` as deprecated and ignored (the refresh uses `FileCacheMiddleware`'s filter); add the caveat that on the shipped `php:8.4-apache` (mod_php) image the refresh runs synchronously, so a stale hit waits for the upstream; keep the `evictOn` advice.
- `docs/creating-middlewares.md` `CacheStalenessMiddleware` entry: align with the guide. Leave §4 *Short-Circuit the Request Chain* unchanged (the request chain is not modified).

### Step 2 — Update cache-configuration and handler docs

- `docs/guides/tent/cache-configuration.md` (~line 213): replace "configure it with the same header-filtering options" with "its background-refresh writes reuse `FileCacheMiddleware`'s header filter automatically; its own header-filter options are deprecated". Keep the matching-`evictOn` advice. Recheck the line ~116 `skipCache` and line ~145 symlink notes still read correctly.
- `docs/request-handlers.md` (~line 226): same header-filter update.
- `docs/guides/tent/creating-request-hashers.md` (~lines 130, 190): verify the memoized-hash statements remain true; reword only if needed.
- `docs/file-cache-middleware-matchers.md` (~line 259): verify; likely unchanged.

Verify every configuration example is valid against `Configuration::buildRule()` after the `tent` changes.

## Files to Change
- `docs/guides/tent/middlewares.md` — staleness section.
- `docs/creating-middlewares.md` — staleness entry.
- `docs/guides/tent/cache-configuration.md` — header-filter sharing note.
- `docs/request-handlers.md` — header-filter sharing note.
- `docs/guides/tent/creating-request-hashers.md` — verify/reword.
- `docs/file-cache-middleware-matchers.md` — verify.

## Notes

- Run after `tent`'s changes land in the branch so wording matches the code (log message, option names).
