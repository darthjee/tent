# Issue: New middleware for query parameters filtering

## Description

Add a new middleware capable of filtering the query parameters of incoming requests. In configuration, it receives a list of parameter names and a mode (`allow` or `deny`) controlling whether that list is treated as an allow-list or a deny-list. `DefaultProxyRequestHandler` also gets a new opt-in build option to auto-initialize this middleware, inserted before `FileCacheMiddleware` in its middleware stack.

## Problem

There is currently no way to strip or restrict query parameters on incoming requests before they're proxied upstream or used to derive the cache key. Because `QueryRequestHasher` derives the cache key solely from the raw query string, any irrelevant or attacker-controlled query parameter (tracking/analytics params, cache-busting noise) causes an unbounded number of distinct cache entries for what is effectively the same request, fragmenting the cache and increasing load on the upstream. There's also no way to prevent unvetted query params from reaching the proxied upstream at all.

## Expected Behavior

- A rule configured with `Tent\Middlewares\FilterQueryParamsMiddleware` (directly via `middlewares`/`prependMiddlewares`, or indirectly via `DefaultProxyRequestHandler`'s new `filter_query_params` option) filters the query string of every incoming request it processes according to its `mode` (`allow`/`deny`) and `params` list, before the request is proxied upstream.
- When wired into `DefaultProxyRequestHandler`, filtering happens before `FileCacheMiddleware`, so the cache key reflects the already-filtered query string.
- Requests with an empty query string, or configured with an empty `params` list, pass through per the documented edge-case rules (see Solution) rather than erroring.
- Existing configurations that don't opt into `filter_query_params` see no behavior change: query params continue to pass through untouched, exactly as today.

## Solution

### Scope Boundaries

- New middleware class: `Tent\Middlewares\FilterQueryParamsMiddleware`.
- Supports **both** allow-list and deny-list modes (not allow-list only), via a `mode` config key:
  - `'mode' => 'allow'` (default when omitted) — only the listed params survive; everything else is stripped.
  - `'mode' => 'deny'` — the listed params are stripped; everything else survives.
- The list of parameter names is passed under the `params` config key.

Example configuration:

```php
[
    'class' => 'Tent\\Middlewares\\FilterQueryParamsMiddleware',
    'params' => ['id', 'page'],
    'mode' => 'allow' // optional, defaults to 'allow'
]
```

- `DefaultProxyRequestHandler` gets a new build option to auto-initialize this middleware, placed before `FileCacheMiddleware` in `initializeMiddlewares()`, consistent with "before the cache middleware" in the original ask. See Backward Compatibility for the option's exact shape and default.
- `FilterQueryParamsMiddleware::build()` validation:
  - `params` is optional, defaulting to `[]` when omitted (same effect as passing an empty array — see Edge Cases).
  - `mode` is optional, defaulting to `'allow'` when omitted. An explicitly-provided value other than `'allow'`/`'deny'` throws `\InvalidArgumentException`, consistent with how `DefaultProxyRequestHandler::build()` already throws for a missing required `'host'`.

### Backward Compatibility

- `ProcessingRequest` currently has no way to mutate the query string (only `setRequestPath()` exists for the path). Add `setQuery(string $query): string`, mirroring `setRequestPath()` exactly — memoizes on the instance only, doesn't touch the underlying `Request`. Purely additive; nothing existing calls or overrides it today.
- `DefaultProxyRequestHandler`'s new option must be **opt-in**. Query params currently pass through proxied requests untouched (and feed `QueryRequestHasher`'s cache key), so defaulting the middleware to "on" would silently strip params — and change cache keys — for every existing deployment.
  - New build param, e.g. `'filter_query_params' => ['params' => [...], 'mode' => 'allow']`.
  - Absent/`null` (the default) → `FilterQueryParamsMiddleware` is not added; current pass-through behavior is preserved exactly.
  - When present, it's passed straight through to `FilterQueryParamsMiddleware::build()` and inserted before `FileCacheMiddleware` in `initializeMiddlewares()` — so caching keys off the already-filtered query string.

### Edge Cases

- **Parsing/rebuilding**: use PHP's built-in `parse_str()`/`http_build_query()` rather than a custom parser. This gives standard semantics for free:
  - Duplicate scalar keys (`?a=1&a=2`) collapse to the last value, per normal PHP behavior.
  - Array-style params (`?a[]=1&a[]=2`, `?a[b]=1`) are grouped by `parse_str` and filtered against the `params` list by their **top-level key name only** (e.g. `a`), not per-index/sub-key.
  - Encoding is normalized through the parse/rebuild round-trip (`http_build_query`'s default RFC1738 encoding), rather than preserved byte-for-byte from the original query string.
- **Order**: surviving params keep their original appearance order from the incoming query string — the middleware only adds/removes params, it does not reorder or otherwise canonicalize the query string. (Canonicalizing param order to improve cache-key hit rates across equivalently-ordered requests is a separate concern, out of scope for this issue.)
- **Empty query string**: no-op, request passes through unchanged.
- **Empty `params` list**: in `allow` mode, strips every param (result is an empty query string); in `deny` mode, strips nothing (no-op) — both are the natural consequence of the filter logic, not special-cased.
- **Params in the list that aren't present in the query**: no effect, since there's nothing to include/exclude.

### Alternative Solutions

Two alternatives were considered and rejected in favor of a general `Middleware`:

1. **Hardcode filtering into `ProxyRequestHandler`/`DefaultProxyRequestHandler`** instead of a standalone middleware. Rejected because it would only work for proxy handlers, whereas a `Middleware` can be attached via `middlewares`/`prependMiddlewares` to any rule in `Configuration::buildRule()` — consistent with how every other cross-cutting concern (header renaming, path rewriting, header injection) is already implemented in this codebase.
2. **Filter only at the cache-key level**, via a new `RequestHasher` that hashes just the allowed subset of params, leaving the actual outgoing request (and its query string) untouched. Rejected because the issue explicitly asks for arriving requests to have their query parameters filtered — i.e. the outgoing request itself should change, not just how it's cached. (This isn't mutually exclusive with the chosen approach and could still be added later for finer-grained hashing control, but it's a separate concern.)

The chosen middleware approach covers both goals at once: it changes the outgoing request, and — because it runs before `FileCacheMiddleware` — the cache key derived by `QueryRequestHasher` reflects the filtered query string too.

### Testing Strategy

Follows the project's existing per-class test folder convention (e.g. `RenameHeaderMiddleware/`, `SetHeadersMiddleware/`):

- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareTest.php` — covers `processRequest()` behavior: allow mode, deny mode, empty query, empty `params` list, duplicate/array-style query keys, params-not-present-in-query, order preservation.
- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareBuildTest.php` — covers `build()`: `params` defaulting to `[]` when omitted, `mode` defaulting to `allow` when omitted, and `\InvalidArgumentException` for an unrecognized `mode` value.
- `source/tests/unit/lib/models/ProcessingRequest/...` — extend (or add alongside) existing `ProcessingRequest` coverage for the new `setQuery()`, mirroring how `setRequestPath()` is already tested.
- `DefaultProxyRequestHandler` — extend `DefaultProxyRequestHandlerBuildTest.php` for the new `filter_query_params` option, and add/extend a scenario test confirming: (a) the middleware is absent when the option is omitted (no behavior change for existing configs), (b) when present, it runs *before* `FileCacheMiddleware` so the cache key reflects the filtered query — likely alongside the existing `DefaultProxyRequestHandlerCachedTest.php`.

## Benefits

- **Cache-poisoning / cache-busting mitigation**: because `QueryRequestHasher` derives the cache key solely from the query string, an attacker (or a client appending arbitrary tracking/analytics params) can currently force unbounded cache misses by varying an irrelevant query param on every request. Placing this middleware before `FileCacheMiddleware` directly mitigates that: once irrelevant params are stripped (allow-list mode) or known-noisy params are dropped (deny-list mode), requests that only differ in filtered-out params collapse to the same cache key — reducing cache fragmentation and load on the upstream.
- **Reduced upstream exposure**: params not on the allow-list (or explicitly denied) never reach the proxied upstream at all, which can also serve as a lightweight way to stop unexpected/unvetted query params from leaking into backend logs or handlers that weren't designed to receive them.
- **Performance overhead**: negligible — `parse_str()`/`http_build_query()` on a single request's query string is O(number of params), not a measurable cost next to the network I/O of proxying.
- **No new attack surface introduced**: the middleware only removes/keeps existing params; it doesn't execute or interpret param values, so it doesn't itself introduce injection or parsing vulnerabilities beyond what PHP's own `parse_str()` already carries (well-understood, not attacker-influenceable beyond normal query-string parsing).
