# Issue: ProcessingRequest: proxy-internal cache-skip flag honoured by FileCacheMiddleware

## Background

`FileCacheMiddleware` decides whether to bypass the cache from exactly one source: the request/response header named by `skip_cache_header`. Since #306, both cache middlewares also accept `evictOn` (or `evict_on`), so on `main` a cache entry can be removed in three places:

| # | Location | Trigger | How this issue gates it |
| --- | --- | --- | --- |
| 1 | `FileCacheMiddleware::processRequest()` | A cached entry whose stored status matches `evictOn` is removed and treated as a miss | Runs after `shouldSkipCache()` |
| 2 | `FileCacheMiddleware::processResponse()` | An upstream response whose status matches `evictOn` removes any existing entry for the key | Runs after `shouldSkipCacheWrite()`, which ORs `shouldSkipCache($response->request())` |
| 3 | `BackgroundRefresher::replaceCache()` / `evictCache()` | A stale refresh scheduled by `CacheStalenessMiddleware` (2xx replaces; status accepted by its `EvictionPolicy` evicts) | Only through an explicit guard in `CacheStalenessMiddleware::processRequest()` |

Only these two middlewares (through `BackgroundRefresher`) read, write or remove cache entries, so gating them covers the whole cache surface.

Relevant current behaviour:

| Location | Behaviour today |
| --- | --- |
| [`FileCacheMiddleware::shouldSkipCache()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/FileCacheMiddleware.php) | Returns false when `skipCacheHeader` is null; otherwise returns true when the configured header is present (case-insensitive) in `$request->headers()`. |
| [`FileCacheMiddleware::processRequest()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/FileCacheMiddleware.php) | Returns untouched when `shouldSkipCache()`; otherwise evaluates matchers; evicts an entry whose stored status matches `evictOn`; otherwise serves an existing entry via `setResponse()`. |
| [`FileCacheMiddleware::processResponse()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/FileCacheMiddleware.php) | Returns untouched when `shouldSkipCacheWrite()`; otherwise evicts when the response status matches `evictOn`; otherwise stores via `ResponseCacher` when all matchers pass. |
| [`CacheStalenessMiddleware::processRequest()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Acts only when `hasResponse()`; when the entry is older than `maxAgeSeconds`, writes a `.refreshing` sentinel and schedules a `BackgroundRefresher` built with its own `EvictionPolicy`. |
| [`RequestInterface`](https://github.com/darthjee/tent/blob/main/source/source/lib/models/RequestInterface.php) / [`Response::request()`](https://github.com/darthjee/tent/blob/main/source/source/lib/models/Response.php) | The interface has no cache-related member. `Response::request()` is typed `: RequestInterface` and falls back to `new Request()` when no request is attached, so anything read from it must exist on the interface. |
| [`ProcessingRequest::cacheHash()` / `setCacheHash()`](https://github.com/darthjee/tent/blob/main/source/source/lib/models/ProcessingRequest.php) | Existing per-request memoized state used by `FileCache`; never serialized into a header. The pattern this flag mirrors. |

## Problem

A guard middleware that must force a cache bypass has no in-process way to say so — it has to inject a marker header into the request. That workaround has three costs:

- the marker is forwarded to the upstream host (`BackgroundRefresher::fetch()` replays `$request->headers()`);
- the marker becomes indistinguishable from a header the client sent;
- the guard is coupled to each rule's `skip_cache_header` name, so it breaks if a rule renames or omits that option.

Requested by darthjee/citadel#41 (per-caller cache extensions, part of darthjee/citadel#33): a guard must bypass the cache — read, write **and eviction** — when the `access_token` cookie is duplicated, malformed, expired or undecodable.

## Expected Behavior

- `new ProcessingRequest()->cacheSkipped()` is `false`; after `skipCache()` it is `true`; a second `skipCache()` keeps it `true`.
- With the flag set: an existing entry is not served and the upstream is contacted.
- With the flag set: a response passing every matcher is not stored; a pre-existing entry keeps its body and `timestamp`.
- With the flag set: an entry whose stored status matches `evictOn` survives `processRequest()`, and a pre-existing entry survives an upstream response whose status matches `evictOn`.
- With the flag set: `CacheStalenessMiddleware` builds no `BackgroundRefresher` and writes no sentinel.
- With the flag set: neither the upstream request nor the final `Response` carries any added header.
- With the flag unset: `skip_cache_header` / `require_cache_header` / matcher / `evictOn` behaviour is unchanged.
- A guard in `prependMiddlewares` bypasses both read and write of a `default_proxy` handler's built-in cache; a guard in `middlewares` bypasses only the write (documented, test-pinned).
- `Request::cacheSkipped()` is `false` and `Request::skipCache()` is a no-op.

## Solution

### API

```php
interface RequestInterface
{
    public function skipCache(): void;

    public function cacheSkipped(): bool;
}

class ProcessingRequest implements RequestInterface
{
    private bool $cacheSkipped = false;

    public function skipCache(): void
    {
        $this->cacheSkipped = true;
    }

    public function cacheSkipped(): bool
    {
        return $this->cacheSkipped;
    }
}

class Request implements RequestInterface
{
    public function skipCache(): void
    {
    }

    public function cacheSkipped(): bool
    {
        return false;
    }
}
```

### Semantics

1. **One-way.** `ProcessingRequest::skipCache()` sets a private boolean to true. No `clearSkipCache()`, no `setCacheSkipped(bool)`, no constructor attribute (not in `ProcessingRequest::ATTRIBUTES`). A later middleware cannot undo it (fail closed).
2. **Default false.** `cacheSkipped()` returns `false` until `skipCache()` is called, including for a `ProcessingRequest` built with an empty `$params` array.
3. **Declared on `RequestInterface`**, because `FileCacheMiddleware::processResponse()` reads the flag through `Response::request()`.
4. **`Request` never claims a skip.** `skipCache()` is a no-op and `cacheSkipped()` returns `false`: a bare `Request` (e.g. the `Response::request()` fallback) is not proxy state.
5. **Never a header, never serialized.** The flag is not added to `$request->headers()`, so it reaches neither the upstream call nor the client response, and `FileCache::buildMeta()` is untouched.
6. **`FileCacheMiddleware`:** `shouldSkipCache()` gains the flag as its first check (`if ($request->cacheSkipped()) { return true; }`). Because `shouldSkipCacheWrite()` already ORs `shouldSkipCache($response->request())`, this one insertion covers the read path, the write path, and both `evictOn` removal branches (paths 1 and 2). With the flag unset, `skip_cache_header`, `require_cache_header` and matcher behaviour stay byte-for-byte identical.
7. **`CacheStalenessMiddleware`:** `processRequest()` gains an explicit first-line guard (`if ($request->cacheSkipped()) { return $request; }`), before the `hasResponse()` check. No `BackgroundRefresher` is built, so neither `replaceCache()` nor `evictCache()` runs for a skipped request (path 3), whatever `evictOn` says. The guard keeps the guarantee local to the class that owns refresh-time eviction, independent of chain wiring. On `main` this middleware is currently never reached through a handler on a hit (darthjee/tent#311); the guard is still required — it becomes live once #311 is fixed, and its unit test pins the contract today.
8. **No reason introspection.** Only `cacheSkipped(): bool` is exposed. Diagnostics stay with the middleware that set the flag; `FileCacheMiddleware` adds no log line, and its `serving from cache` debug line simply never fires for a skipped read.
9. **No handler change.** The same `ProcessingRequest` instance flows through `DefaultProxyRequestHandler`'s built-in `FileCacheMiddleware` and every rule middleware.

### Cache cycle behaviour

| Flag state | `FileCacheMiddleware::processRequest()` | Handler / upstream | `FileCacheMiddleware::processResponse()` | `CacheStalenessMiddleware::processRequest()` |
| --- | --- | --- | --- | --- |
| unset | normal matchers, eviction and cache hit | upstream called only on a miss | normal eviction / store | normal staleness evaluation and refresh |
| set | returns immediately; nothing read or removed | upstream always called | returns immediately; nothing stored or removed | returns immediately; no refresh, no sentinel |
| set, rule also sets `skip_cache_header` | returns immediately (flag wins) | upstream always called | returns immediately | returns immediately |

### Middleware placement (`default_proxy` ordering)

**Why it matters.** `RequestHandler::applyRequestMiddlewares()` returns at the first middleware whose result reports `hasResponse()`. On a cache hit, `FileCacheMiddleware::processRequest()` calls `setResponse()`, so every later middleware is never invoked. The final `default_proxy` order is `prependMiddlewares` → internal defaults (`RenameHeaderMiddleware`, `SetHeadersMiddleware`, optional `FilterQueryParamsMiddleware`, `FileCacheMiddleware`) → `middlewares`. A guard in `middlewares` therefore gets the write bypass but never the read bypass — silently, since write-only tests still pass.

**Decision: `prependMiddlewares` is the only supported placement for a full bypass.** Documented in the `FileCacheMiddleware` docblock, `docs/creating-middlewares.md` and `docs/guides/tent/cache-configuration.md`, and pinned by a negative ordering-contract test. This matches existing placement guidance (`creating-middlewares.md` already directs authors to `prependMiddlewares`; `CacheCleanupMiddleware` and `FilterQueryParamsMiddleware` carry similar "place it before" advice). The requester can declare its guard in `prependMiddlewares` from day one.

Rejected / deferred:

- **Debug warning when a skip intent is not honoured** — dropped: on a hit the guard never runs, so nothing knows a skip was intended; a generic "chain short-circuited" warning would fire on every ordinary hit.
- **Full reorder (`FileCacheMiddleware` after rule middlewares)** — rejected: lets the handler's default `Host` rewrite overwrite a user's `SetHeadersMiddleware`, contradicts `docs/request-handlers.md` / `docs/creating-middlewares.md`, and changes every existing `default_proxy` rule.
- **Surgical reorder (move only `FileCacheMiddleware` after `middlewares`)** — possible follow-up only: no current need, and it changes what gets stored for every rule whose middlewares rewrite responses.

**Relation to darthjee/tent#311.** Same root (the short-circuit), different problem. Its likely fix — letting opted-in middlewares run after a response is set — would make a `middlewares` guard *run* on a hit, but the cached response would already be set; honouring the flag would require retracting a response. Fixing #311 does not lift this placement rule.

### Alternatives considered

The requester already keys per-caller entries through a custom `RequestHasher`, so the guard needs only a binary "do not use the cache for this request" signal.

| # | Approach | Verdict |
| --- | --- | --- |
| A | Marker header (today's workaround) | Rejected — see Problem. |
| B | One-way boolean on `ProcessingRequest` | **Chosen** — typed, fail-closed, never serialized, mirrors `cacheHash()`. |
| C | Generic attribute bag (`setAttribute('tent.skip_cache', true)`) | Rejected — stringly typed, overwritable/unsettable (not fail-closed), not type-checkable through `RequestInterface`; no second per-request value needs sharing. |
| D | Granular decision (`skipRead()` / `skipWrite()`, or a `CacheMode` enum) | Rejected for now — the requester needs a full bypass. `skipCache()` deliberately means *full* bypass; a future "skip read, allow write" (forced refresh) gets its own method rather than changing `skipCache()`'s meaning. |
| E | Hasher decides the skip (returns `null` / throws) | Rejected — invoked late inside each cache consumer, after matchers, and would make every `RequestHasher` responsible for cache policy. |
| F | Injected skip predicate per cache middleware (`'skip_when' => Checker::class`) | Rejected — must be wired on every cache middleware in every rule; forgetting one yields a partial bypass. |
| G | Force a miss with a random `setCacheHash()` salt | Rejected — writes a junk entry per bypassed request and can still evict under that key. |
| H | Guard short-circuits with its own upstream response | Rejected — duplicates proxying and skips downstream middleware processing. |

### Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Where the flag is checked in `FileCacheMiddleware` | Inside `shouldSkipCache()`, flag first | One insertion covers read, write and both `evictOn` branches; short-circuits header normalization and makes intent obvious. |
| Eviction guarantee | `shouldSkipCache()` covers paths 1–2; explicit guard in `CacheStalenessMiddleware` covers path 3 | All three removal paths are gated; a skipped request never evicts (see Performance & security). |
| Configuration surface | No constructor attribute, header or configuration key sets the flag; only middleware code can | Rule configuration is operator-controlled, but any declarative trigger the client can satisfy (e.g. header presence) would recreate `skip_cache_header`. Code can actually validate request content. |
| Header filtering | Unchanged | `mode` / `excluded_headers` / `allowed_headers` decide which stored headers survive — independent of whether a response is stored at all. |

## Scope

### In scope

1. `skipCache()` / `cacheSkipped()` on `RequestInterface`, `ProcessingRequest` (real flag) and `Request` (no-op / `false`).
2. `FileCacheMiddleware`: flag-first check in `shouldSkipCache()`.
3. `CacheStalenessMiddleware`: explicit first-line guard in `processRequest()`.
4. Tests listed under Testing, including the negative ordering-contract test.
5. Docs: `FileCacheMiddleware` docblock, `docs/guides/tent/cache-configuration.md`, `docs/creating-middlewares.md`, and the stale note in `docs/file-cache-middleware-matchers.md`.

### Out of scope

- **The guard logic itself** — deciding *when* to skip belongs to the caller (darthjee/citadel#41). Tent ships only the mechanism.
- **Reordering `default_proxy` middlewares** — see Middleware placement.
- **Fixing the staleness chain short-circuit** — darthjee/tent#311.
- **`CacheCleanupMiddleware`** — ignores the flag and keeps invalidating on mutating methods.
- **Any change to** `Response`, `ResponseCacher`, `FileCache`, `BackgroundRefresher`, `EvictionPolicy` or `DefaultProxyRequestHandler`.
- **A response-side flag** — upstream-driven skips are already covered by `skip_cache_header` on responses.
- **A built-in, config-driven `SkipCacheMiddleware`** (matchers → `skipCache()`) — possible follow-up only. Path/method bypasses are already expressible via `FileCacheMiddleware`'s own `matchers` / `NegativeMatcher`; the motivating triggers (malformed/expired/duplicated token) need code, not declarative matchers; a header-presence matcher would recreate the client-controlled `skip_cache_header`; and it would carry the same placement trap.

## Edge cases

- `skipCache()` called twice, or after a cache hit was already set: the flag stays true; an already-set response is *not* retracted.
- Flag set with a valid cache entry: the entry is neither served, overwritten nor removed; later non-skipped requests keep receiving it.
- Flag set with a non-2xx upstream response, including a status matched by `evictOn`: nothing is stored or removed; the pre-existing entry survives.
- Flag set with a cached entry whose stored status matches `evictOn`: this request does not remove it; the next non-skipped request evicts it as usual.
- `Response::request()` returning the bare `Request` fallback: `cacheSkipped()` is `false`, so the write path behaves exactly as today.
- One middleware sets the flag and a later one appends headers: the headers still flow upstream. The flag suppresses caching only, never the request.
- A refresh already in flight for the same key (sentinel present): the flagged request ignores it; the refresh may still replace the entry. The flag is per-request, not per-key.
- A refresh started by an earlier non-skipped request is unaffected: the flag is never serialized and `BackgroundRefresher` holds its own request.
- Conditional requests (`If-None-Match` / `If-Modified-Since`): the cache is not read, so the client's conditional headers go upstream verbatim; an upstream 304 passes through and is not stored.
- Any HTTP method: the flag is checked before every matcher, including `RequestMethodMatcher`.
- Several `FileCacheMiddleware` instances on one rule: all consult the same `ProcessingRequest`, so the flag applies consistently.

## Performance & security

### Security

- **No cross-caller leakage (primary reason for the write bypass).** In the requester's case, a duplicated or malformed `access_token` makes the per-caller hasher fall back to the *shared* key, while the upstream may still authenticate one of the tokens. Without the write bypass, that private response would be stored under the shared key and served to every caller.
- **No eviction DoS.** A skipped request never removes entries, even when the entry or the upstream response looks evictable. Otherwise, with `evictOn: ['4xx']`, any caller sending a garbage cookie (upstream → 401) could wipe shared entries for everyone.
- **Not client-settable.** Not in `ATTRIBUTES`, not derived from any header, no configuration key.
- **No leakage.** Never a header — absent from both the upstream request and the client response.
- **Fail closed.** One-way; no downstream middleware can clear it.
- **No logging in Tent.** Keeping tokens and payloads out of logs is the guard's responsibility.
- **`CacheCleanupMiddleware`** still runs for flagged mutating requests (existing behaviour, explicit non-goal).

### Performance

- **Unset flag:** one boolean method call per `shouldSkipCache()` call — negligible.
- **Flagged requests are cheaper inside Tent:** the check returns before any `FileCache` is built — no disk read, no `exists()` / `httpCode()` lookup, no write, no staleness sentinel.
- **Upstream load amplification:** every flagged request reaches the upstream. If a guard's trigger is client-controllable, any client can force cache misses — the same exposure `skip_cache_header` already has. Tent cannot mitigate it (rate limiting belongs to the guard owner or infrastructure), but the cache guide must state it.

## Backward compatibility

- **Interface change.** `Tent\Models\RequestInterface` gains two methods. In-repo, only `Tent\Models\Request` and `Tent\Models\ProcessingRequest` implement it; `dev/api`'s `ApiDev\RequestInterface` (and its `MockRequest`) is a separate interface and unaffected. No documented extension point (middlewares, matchers, handlers, request hashers) asks users to *implement* `RequestInterface`, so it is not treated as a third-party implementation surface.
- **Release.** Ship as a **minor** release (1.3.0). The release notes must carry an upgrade note: "`RequestInterface` gained `skipCache()` / `cacheSkipped()`; any external implementer must add them."
- **Flag unset:** behaviour unchanged bit for bit; no new configuration key, no migration.
- **Cache format** unchanged, so existing on-disk entries stay valid across the upgrade.
- **Middleware ordering** unchanged; the ordering-contract test pins today's behaviour.

## Implementation notes

- Update both in-repo `RequestInterface` implementations in the same commit: `ProcessingRequest` (real behaviour) and `Request` (no-op / `false`).
- No new files, so no `loader.php` change.
- `FileCacheMiddleware` docblock: document the flag next to `skip_cache_header` and name `prependMiddlewares` explicitly as the required placement for a full bypass.
- `docs/guides/tent/cache-configuration.md`: a short section on the flag — the only documented way for a middleware to force a bypass without a header — including the `prependMiddlewares` placement rule and the load-amplification warning ("every flagged request goes upstream; if your trigger is client-controllable, so is the bypass").
- `docs/creating-middlewares.md`: cache guards belong in `prependMiddlewares`.
- `docs/file-cache-middleware-matchers.md`: fix the stale note ("no built-in cache expiry or invalidation mechanism"), which predates `evictOn` from #306.
- Expected diff: two models, one interface, two middlewares, docblocks, three doc pages.

## Testing

`source/tests/unit/lib/models/ProcessingRequest/` — new spec file, e.g. `ProcessingRequestCacheSkipTest.php`:

- `cacheSkipped()` is `false` on a request built with no params and on one built with unrelated params;
- `cacheSkipped()` is `true` after `skipCache()`, and stays `true` after two calls.

Extend `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php`:

- flag set and an entry on disk: `hasResponse()` is `false` and no cache hit is logged;
- flag set: matchers are not consulted (the entry is untouched);
- flag set, `evictOn` configured, entry whose stored status matches: the entry is still on disk;
- flag unset: existing cache-hit specs pass unchanged.

Extend `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php`:

- flag set: a cacheable 2xx response is not stored;
- flag set with a pre-existing entry: body and `timestamp` unchanged;
- flag set with `require_cache_header` configured: nothing stored, no header added;
- flag set, `evictOn: ['4xx']`, a pre-existing entry and a 401 response: the entry is still on disk;
- flag unset: existing store/skip specs pass unchanged.

Extend `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php`:

- flag set on a request carrying a stale cached response: the injected scheduler is never invoked and no `.refreshing` sentinel is written;
- the same with `evictOn` configured: no refresh and the entry is not removed;
- flag unset: existing refresh-scheduling specs pass unchanged.

`DefaultProxyRequestHandler` spec (new or existing):

- a guard calling `skipCache()` in `prependMiddlewares` bypasses the built-in cache for both read and write;
- neither the recorded upstream call nor the returned response headers contain a marker header;
- **ordering contract:** a guard calling `skipCache()` in `middlewares` does **not** bypass the read path (the cached response is still served).

Follow the repository convention of `Configuration::reset()` in `setUp()` and `Logger::setInstance(new NullLoggerInstance())` for silence. Run with `docker compose run --rm tent_tests composer tests:unit`.

## Context

- Origin: darthjee/citadel#41 (per-caller cache extensions), part of darthjee/citadel#33.
- Related: #306 introduced `evictOn` on both cache middlewares; this issue gates all of its removal paths.
- Related: darthjee/tent#311 — `CacheStalenessMiddleware` is never reached through a handler on a cache hit; this issue's staleness guard becomes live once that is fixed.
- Affected modules: `Tent\Models` (`ProcessingRequest`, `Request`, `RequestInterface`), `Tent\Middlewares` (`FileCacheMiddleware`, `CacheStalenessMiddleware`), `Tent\RequestHandlers` (test coverage only).
