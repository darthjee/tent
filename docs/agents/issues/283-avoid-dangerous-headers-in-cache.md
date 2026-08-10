# Issue: Avoid dangerous headers in cache

## Description

`FileCacheMiddleware` stores every response header verbatim in its cache metadata file (`FileCache::buildMeta()` reads `$response->headers()` wholesale). Since a cache entry is later replayed to *any* future client that hits the same cache key, this includes session/credential-bearing headers — most notably `Set-Cookie` — which get silently replayed to clients other than the one that originally received them.

The original response to the client that triggered the write is unaffected either way — this is purely about what gets persisted into the cache file and later served on subsequent cache *hits*.

There's also a second, independent write path: `CacheStalenessMiddleware` → `BackgroundRefresher::replaceCache()` re-stores a refreshed response via its own `ResponseCacher` call, entirely separate from `FileCacheMiddleware::processResponse()`.

## Problem

There is no way to exclude specific headers from what gets written to the cache. A response that legitimately needs to be cached (e.g. its body/other headers are safe and worth reusing) can't have just its dangerous headers stripped — today it's all-or-nothing at the header level, and nothing at all is filtered.

Concrete example: an endpoint listing all NPCs (including hidden ones) might set `Set-Cookie` alongside a genuinely useful, harmless header (e.g. pagination info). Skipping caching of the whole response to avoid caching the cookie would throw away the caching benefit for the harmless part.

Any fix also needs to cover **both** cache-write call sites — `FileCacheMiddleware` and `CacheStalenessMiddleware`'s background-refresh path — or the leak just moves to whichever path isn't covered.

## Expected Behavior

- The response returned to the originating client is unaffected — headers are stripped only from what's written to the cache file, never from the live response.
- Out of the box, with zero configuration, a curated default list of dangerous headers is never stored in the cache (safe by default).
- Callers can override or extend that default list, or switch to an explicit allowlist instead, via configuration on `FileCacheMiddleware`.
- `CacheStalenessMiddleware`'s background-refresh writes get the same protection as `FileCacheMiddleware`'s direct writes — no gap between the two write paths.
- `DefaultProxyRequestHandler` exposes the same configuration surface, so proxies built on it get the same control without touching `FileCacheMiddleware` directly.
- A debug-level log line records when a write actually stripped headers, to help operators troubleshoot "why isn't header X being cached" — logging the stripped header *names* only, never their values, so the log itself can't reintroduce the leak this issue closes.

## Solution

### Default dangerous headers

A curated default list, excluded from cache storage unless overridden (case-insensitive match):

- `Set-Cookie` — leaks one client's session cookie to every future cache hit
- `Set-Cookie2` — obsolete cookie header, same risk
- `WWW-Authenticate` — carries auth-challenge/realm details tied to the original request context
- `Proxy-Authenticate` — same concern, proxy-auth variant

`Authorization`/`Proxy-Authorization` were considered but excluded from the default list: they are *request* headers per HTTP semantics and shouldn't normally appear in `$response->headers()` unless a backend echoes them back.

Exposed as a public constant, `FileCacheMiddleware::DEFAULT_EXCLUDED_HEADERS`, following the existing `DEFAULT_TARGETS` pattern in `CacheCleanupMiddleware`, so callers can reference it (e.g. when building an "additional headers" list).

### Option shape

Following the codebase's snake_case config-key style (`skip_cache_header`, `require_cache_header`, `filter_query_params`), and mirroring `FilterQueryParamsMiddleware`'s `mode` switch:

- `mode` — `'deny'` (default) or `'allow'`. Invalid values throw `\InvalidArgumentException`, same as `FilterQueryParamsMiddleware`. `mode` is the single source of truth for which of the options below are consulted — the others are simply not read, no error if set alongside the "wrong" mode.
- `excluded_headers` *(deny mode)* — full override of the excluded-headers list. When omitted, the base is `DEFAULT_EXCLUDED_HEADERS`.
- `additional_excluded_headers` *(deny mode)* — always merged on top of the resolved base (`excluded_headers ?? DEFAULT_EXCLUDED_HEADERS`, plus `additional_excluded_headers`), regardless of whether `excluded_headers` was passed.
- `allowed_headers` *(allow mode)* — explicit, complete list of the only headers kept in storage; everything else is stripped. No default list, no append option — this mode exists for callers who know exactly what they want cached rather than maintaining a denylist. Omitted/empty while `mode: 'allow'` throws `\InvalidArgumentException` (an empty allowlist has no legitimate use — it would silently strip every header).

Header name matching is case-insensitive throughout, splitting each raw `"Header-Name: value"` line on the **first** colon only (values can contain colons, e.g. `Expires` dates) and comparing the name exactly — never a substring match. A response can carry multiple headers with the same name (e.g. more than one `Set-Cookie`); filtering drops *all* matching lines, not just the first.

`excluded_headers: []` (explicit empty override) is allowed and deliberately disables all default protection — no non-overridable floor, consistent with every other override option in this codebase.

### Architecture — where filtering happens

Write-path trace: `FileCacheMiddleware::processResponse()` → `new FileCache(...)` → `(new ResponseCacher($cache, $response))->process()` → `$cache->store($response)` → `FileCache::buildMeta()` (currently reads `$response->headers()` wholesale, unfiltered).

The second write path — `CacheStalenessMiddleware` → `BackgroundRefresher::replaceCache()` — also calls `(new ResponseCacher($this->cache, $response))->process()` directly. Both call sites already funnel through `ResponseCacher`, which is the natural shared choke point, rather than each middleware individually wrapping the `Response` before handing it off (a per-caller approach that only protects the caller that remembers to do it).

Introduce a small `HeaderFilter` interface (`filter(Response $response): Response`) with two implementations, one per `mode`:

- `Tent\Content\ExcludedHeaderFilter` (deny mode)
- `Tent\Content\AllowedHeaderFilter` (allow mode)

Each returns a **clone** of the response with filtered headers (`clone` + `setHeaders()`), leaving body/httpCode/request untouched. `FileCacheMiddleware`/`CacheStalenessMiddleware` pick which implementation to build based on their configured `mode`. `ResponseCacher` takes a `?HeaderFilter` as an optional collaborator and applies it internally before storing:

```php
class ResponseCacher
{
    public function __construct(
        Cache $cache,
        Response $response,
        ?HeaderFilter $headerFilter = null
    ) {
        $this->cache = $cache;
        $this->response = $response;
        $this->headerFilter = $headerFilter;
    }

    public function process(): void
    {
        if (!$this->cache->exists()) {
            $response = $this->headerFilter?->filter($this->response) ?? $this->response;
            $this->cache->store($response);
        }
    }
}
```

Every write — current and any future call site — funnels through this one place; nothing can "forget" to filter. This keeps `FileCache`/`buildMeta()` completely untouched, and naturally extends to a list/pipeline of filters later without further changes to either middleware.

Considered and deferred: consolidating this with the existing `X-Cache-Time` header injection (currently in `FileCache::headers()`, on the *read* path when serving a cache hit) under one shared "header processor" concept. Different lifecycle and caller from this issue's write-path concern — noted as a future refactor, not done here.

### DefaultProxy wiring

Follows the existing append-at-the-end pattern (same shape used to thread `require_cache_header` through in #279):

- `FileCacheMiddleware`: new optional constructor params after `$requireCacheHeader` — `?array $excludedHeaders = null, ?array $additionalExcludedHeaders = null, ?array $allowedHeaders = null, string $mode = 'deny'`. Constructor resolves and builds whichever `HeaderFilter` implementation matches `$mode`, stored as `$this->headerFilter`, passed into `ResponseCacher` at write time. Mirrored in `FileCacheMiddleware::build()`, reading `excluded_headers`/`additional_excluded_headers`/`allowed_headers`/`mode` from attributes.
- `DefaultProxyRequestHandler`: matching new private props, trailing constructor params, and `build()` reads for all four keys, passed straight into the `new FileCacheMiddleware(...)` call after `$this->requireCacheHeader`.
- `CacheStalenessMiddleware` gets the **same options** (same defaults/semantics), builds its own `HeaderFilter`, and threads it through `BackgroundRefresher`'s constructor so its own `ResponseCacher` call in `replaceCache()` is filtered too. It is *not* currently wired into `DefaultProxyRequestHandler` at all (configured standalone per-rule today), so no `DefaultProxyRequestHandler` changes are needed for its options.

Appending rather than inserting keeps every existing positional constructor call unchanged.

### Naming vs. #278/#279

No actual name collision — `excluded_headers`/`additional_excluded_headers`/`allowed_headers`/`mode` (this issue) are distinct strings from `skip_cache_header`/`require_cache_header` (#278/#279). But #279's PR title ("header filter configuration for FileCacheMiddleware") already claimed the phrase "header filter" for what is actually an all-or-nothing gate on whether a response is cached at all — not a per-header exclusion. To head off future confusion:

- `FileCacheMiddleware`'s class docblock should explicitly distinguish the two: `skip_cache_header`/`require_cache_header` gate whether a response is cached at all, while the new options control which individual headers are stripped from what gets stored.
- Same distinction called out briefly in `docs/request-handlers.md` where the new options are documented.

No renaming of the existing #278/#279 options — that would be a breaking change for no real benefit.

### Scope boundaries

**In scope:** the `HeaderFilter` interface and both implementations, the `DEFAULT_EXCLUDED_HEADERS` constant, all four new options on `FileCacheMiddleware` and `CacheStalenessMiddleware`, the `ResponseCacher` collaborator change, `DefaultProxyRequestHandler` wiring for `FileCacheMiddleware`'s options, and the docs/docblock updates above.

**Out of scope (explicitly deferred):**

- Consolidating with the `X-Cache-Time` read-path header injection into one shared "header processor" concept.
- Renaming the existing `skip_cache_header`/`require_cache_header` options.
- Any change to how headers are served on a cache *read* — this issue only affects what gets *written* to storage.
- A migration/cleanup path for cache entries already written before the upgrade — they keep whatever headers were stored under the old code until naturally overwritten or removed; operators are not instructed to clear their cache directory.

### Backward compatibility

Non-breaking mechanically: all new constructor params are appended at the end with defaults, so every existing positional call keeps working; the cache meta-file JSON shape (`headers`/`httpCode`/`timestamp`) is unchanged; cache-key derivation is untouched, so hit/miss behavior is unaffected.

There is one real, intended behavior change: with zero config, `DEFAULT_EXCLUDED_HEADERS` now applies automatically, so any deployment currently relying on `Set-Cookie`/`WWW-Authenticate`/etc. being replayed from cache will see that stop working. That's the point of the fix, but it's default-on — worth a changelog/release-note callout as a security-relevant behavior change.

### Performance & security

Filtering only runs on the cache *write* path (a miss that gets stored) — the hot read/hit path is untouched, so cache-hit latency is unaffected. Per-write cost is negligible relative to the upstream HTTP round trip + disk I/O already happening on every cache write. Implement the membership check as a normalized (lowercased) hash-set (`isset()`), not `in_array()`/loop.

Tent runs as a classic per-request PHP-FPM/Apache app (`index.php` requires `configuration/configure.php` fresh every request) — every middleware, including `FileCacheMiddleware`, is rebuilt per request, not once at server startup. The excluded/allowed-list resolution therefore happens per-request too; this is a non-issue, cheap array merging identical in cost profile to every other middleware option already rebuilt per request under this model.

The core security win is closing cross-client session/credential replay via the cache. The two opt-outs (`excluded_headers: []`, `mode: 'allow'` without a matching floor) are intentional escape hatches, not vulnerabilities, but documentation should use a warning tone for them (e.g. "disabling this reintroduces the original cross-client leak risk") rather than a neutral option description.

## Benefits

- Closes a real cross-client information-leak risk in the cache layer (session cookies and auth-challenge headers no longer replayed to arbitrary future clients), safe by default with zero configuration required.
- Covers **both** cache-write paths (`FileCacheMiddleware` and `CacheStalenessMiddleware`'s background refresh) — no gap left for the fix to be silently bypassed.
- Keeps the caching benefit for the rest of a response's headers/body instead of an all-or-nothing skip.
- Flexible enough for advanced callers (custom denylist, or a strict allowlist) without forcing extra configuration on everyone else.
- Centralizing the filter in `ResponseCacher` means any future cache-write call site automatically inherits the same protection.
