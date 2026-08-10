# Plan: Avoid dangerous headers in cache

Issue: [283-avoid-dangerous-headers-in-cache.md](../../issues/283-avoid-dangerous-headers-in-cache.md)

## Overview

`FileCacheMiddleware` and `CacheStalenessMiddleware`'s background-refresh path both persist response headers verbatim into the file cache, so a dangerous header like `Set-Cookie` gets replayed to every future client that hits the same cache entry. This plan introduces a `HeaderFilter` abstraction (deny-list by default, with an explicit allow-list mode) applied centrally inside `ResponseCacher` — the single choke point both write paths already share — and exposes matching configuration on `FileCacheMiddleware`, `CacheStalenessMiddleware`, and `DefaultProxyRequestHandler`.

Single specialist agent involved (`tent` — this repo's only agent with PHP/`source/` scope for this issue); written directly as `plan.md` per this project's convention for a single-agent plan (no `## Agents involved`/`## Shared contracts` split).

## Context

- `FileCache::buildMeta()` (`source/source/lib/content/FileCache.php`) stores `$response->headers()` wholesale — the actual point where headers land on disk, but deliberately left untouched by this plan (see Step 1/Step 3 below for why).
- Two independent write paths converge on `Tent\Service\ResponseCacher::process()`: `FileCacheMiddleware::processResponse()` and `CacheStalenessMiddleware` → `BackgroundRefresher::replaceCache()`. Both must get the same protection.
- Precedent to follow: `FilterQueryParamsMiddleware`'s `mode` (`allow`/`deny`) option and `\InvalidArgumentException` on an invalid value; `CacheCleanupMiddleware::DEFAULT_TARGETS` as the pattern for a public default-list constant; #279's append-at-the-end constructor pattern (`require_cache_header`) for adding options without breaking existing positional calls.
- Full design rationale (why write-time not read-time filtering, why strip-the-header not skip-the-response, why both a deny-list and an allow-list, exact option semantics, edge cases, backward-compatibility notes) is already settled in the issue file — this plan does not re-derive it, only sequences the implementation.

## Implementation Steps

### Step 1 — `HeaderFilter` interface and implementations (`Tent\Content`)

- `source/source/lib/content/HeaderFilter.php` — interface with one method: `filter(Response $response): Response`.
- `source/source/lib/content/ExcludedHeaderFilter.php` — deny-mode implementation.
  - Public constant `DEFAULT_EXCLUDED_HEADERS = ['Set-Cookie', 'Set-Cookie2', 'WWW-Authenticate', 'Proxy-Authenticate']`. Placed here (not on `FileCacheMiddleware`, despite the issue's illustrative `FileCacheMiddleware::DEFAULT_EXCLUDED_HEADERS` naming) so `CacheStalenessMiddleware` can reference the same constant without depending on `FileCacheMiddleware` — see Notes.
  - Constructor takes `array $excludedHeaders` (the already-resolved, already-merged list — merging `excluded_headers ?? DEFAULT_EXCLUDED_HEADERS` with `additional_excluded_headers` is the caller's job, see Step 2). Normalizes to a lowercased hash-set (`array_change_key_case(array_flip($excludedHeaders), CASE_LOWER)` or equivalent) once, at construction.
  - `filter()` clones the `Response`, iterates `$response->headers()` (raw `"Name: value"` strings), splits each on the **first** `:` only (`explode(':', $line, 2)`), compares the trimmed name against the hash-set case-insensitively, drops every matching line (a response can carry more than one header with the same name, e.g. multiple `Set-Cookie`), calls `setHeaders()` on the clone with the surviving lines, returns the clone. Body/httpCode/request are untouched.
  - When at least one header was actually stripped, calls `Logger::debug()` with the stripped header *names* only (never values) — e.g. `'[cache] - stripped headers from cache write: ' . implode(', ', $strippedNames)`, matching the `[status] - reason` convention documented in `docs/agents/architecture.md`.
- `source/source/lib/content/AllowedHeaderFilter.php` — allow-mode implementation, same shape, inverted predicate (keep only headers whose name is in the configured set), same debug-log-on-change behavior for whichever headers got dropped.
- Add all three files to `source/source/loader.php`, interface before implementations, grouped with the existing `content/` block.

### Step 2 — Shared mode/list resolution helper

Both `FileCacheMiddleware` and `CacheStalenessMiddleware` need identical logic to turn `(mode, excluded_headers, additional_excluded_headers, allowed_headers)` into a `?HeaderFilter`. Duplicating it risks the two middlewares drifting out of sync (the exact gap this issue exists to close). Add one shared builder:

- `source/source/lib/content/HeaderFilterBuilder.php` — `public static function build(string $mode, ?array $excludedHeaders, ?array $additionalExcludedHeaders, ?array $allowedHeaders): HeaderFilter`.
  - `$mode === 'deny'`: resolve `($excludedHeaders ?? ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS) + ($additionalExcludedHeaders ?? [])`, return `new ExcludedHeaderFilter($resolved)`.
  - `$mode === 'allow'`: if `$allowedHeaders` is null/empty, throw `\InvalidArgumentException` (no legitimate use for an empty allow-list — see issue's "Edge cases"). Otherwise `new AllowedHeaderFilter($allowedHeaders)`.
  - Any other `$mode` value: throw `\InvalidArgumentException`, mirroring `FilterQueryParamsMiddleware`'s existing guard.
- Add to `loader.php` alongside the Step 1 files.

### Step 3 — `ResponseCacher` takes an optional `HeaderFilter`

`source/source/lib/service/ResponseCacher.php`:
- New optional constructor param: `?HeaderFilter $headerFilter = null`.
- `process()`: when the cache doesn't already exist, apply the filter before storing — `$response = $this->headerFilter?->filter($this->response) ?? $this->response; $this->cache->store($response);`.
- Update the class docblock's constructor param list and usage note.

`FileCache::buildMeta()` is intentionally **not** touched — it keeps reading `$response->headers()` as-is; the `Response` it receives is already filtered by the time it gets there.

### Step 4 — `FileCacheMiddleware` options

`source/source/lib/middlewares/FileCacheMiddleware.php`:
- New constructor params, appended after `$requireCacheHeader`: `?array $excludedHeaders = null, ?array $additionalExcludedHeaders = null, ?array $allowedHeaders = null, string $mode = 'deny'`.
- Constructor builds `$this->headerFilter = HeaderFilterBuilder::build($mode, $excludedHeaders, $additionalExcludedHeaders, $allowedHeaders);` and stores it.
- `processResponse()`: pass `$this->headerFilter` as the third arg to `new ResponseCacher($cache, $response, $this->headerFilter)`.
- `build()`: read `mode` (default `'deny'`), `excluded_headers`, `additional_excluded_headers`, `allowed_headers` from `$attributes`, pass through to the constructor.
- Class docblock: document the four new options, and add an explicit line distinguishing them from `skip_cache_header`/`require_cache_header` (#278/#279) — those gate *whether* a response is cached at all; these control *which headers* are stripped from what gets stored.

### Step 5 — `CacheStalenessMiddleware` + `BackgroundRefresher` get the same protection

This closes the second write path found while scoping the issue.

`source/source/lib/service/BackgroundRefresher.php`:
- New optional constructor param, appended at the end: `?HeaderFilter $headerFilter = null`.
- `replaceCache()`: pass it through — `(new ResponseCacher($this->cache, $response, $this->headerFilter))->process();`.

`source/source/lib/middlewares/CacheStalenessMiddleware.php`:
- Same four new constructor params as `FileCacheMiddleware` (Step 4), same `HeaderFilterBuilder::build()` call, same `build()` attribute reads.
- Wherever it constructs `new BackgroundRefresher($request, $cache, $this->host, $this->httpClient)` (currently line ~205), append `$this->headerFilter` as the fifth arg.
- Class docblock: document the new options; note they should normally match `FileCacheMiddleware`'s configuration on the same rule (same `location`) to avoid inconsistent filtering between a direct cache write and a background-refresh write.

### Step 6 — `DefaultProxyRequestHandler` wiring

`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`:
- New private props: `?array $excludedHeaders`, `?array $additionalExcludedHeaders`, `?array $allowedHeaders`, `string $mode`.
- Constructor: four new trailing params (`$mode` defaulting to `'deny'`) appended after `$requireCacheHeader`, stored as-is.
- `build()`: read `excluded_headers`, `additional_excluded_headers`, `allowed_headers`, `mode` from `$params` (same defaults as above), pass through.
- `initializeMiddlewares()`: pass all four straight into the `new FileCacheMiddleware(...)` call, after `$this->requireCacheHeader`.
- Class docblock: extend the existing options list and add a usage example mirroring the `filter_query_params` example already present.
- `CacheStalenessMiddleware` is not currently wired into `DefaultProxyRequestHandler` at all (configured standalone per-rule) — no change needed there for it.

### Step 7 — Documentation

- `docs/request-handlers.md`: document the four new `default_proxy` options (`mode`, `excluded_headers`, `additional_excluded_headers`, `allowed_headers`), including the #278/#279 disambiguation note from Step 4.

## Files to Change

- `source/source/lib/content/HeaderFilter.php` — new interface
- `source/source/lib/content/ExcludedHeaderFilter.php` — new, deny-mode filter + `DEFAULT_EXCLUDED_HEADERS`
- `source/source/lib/content/AllowedHeaderFilter.php` — new, allow-mode filter
- `source/source/lib/content/HeaderFilterBuilder.php` — new, shared mode/list resolution used by both middlewares
- `source/source/lib/service/ResponseCacher.php` — optional `HeaderFilter` collaborator, applied in `process()`
- `source/source/lib/service/BackgroundRefresher.php` — optional `HeaderFilter` collaborator, passed through in `replaceCache()`
- `source/source/lib/middlewares/FileCacheMiddleware.php` — new options, docblock update
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — new options, docblock update
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — new options, docblock update
- `source/source/loader.php` — `require_once` entries for the four new `content/` files
- `docs/request-handlers.md` — document the new `default_proxy` options
- New/extended PHPUnit tests (see below)

### Tests

Follow the existing per-class test directory convention (`source/tests/unit/lib/<domain>/<Class>/<Class><Concern>Test.php` where a class already has multiple test files, otherwise a single `<Class>Test.php`):

- `source/tests/unit/lib/content/ExcludedHeaderFilterTest.php` — default list applied; override via constructor list; multiple same-name headers all stripped; colon-in-value handled (splits on first `:` only); case-insensitive match; exact-name match (no substring false positives, e.g. `X-Original-Set-Cookie` survives); empty list passed through means nothing stripped; debug log emitted with names only when something is stripped, not emitted when nothing matches.
- `source/tests/unit/lib/content/AllowedHeaderFilterTest.php` — mirrors the above for the inverted predicate.
- `source/tests/unit/lib/content/HeaderFilterBuilderTest.php` — deny mode with/without `excluded_headers`/`additional_excluded_headers`; allow mode with a valid list; allow mode with empty/missing list throws `\InvalidArgumentException`; invalid `mode` string throws.
- `source/tests/unit/lib/service/ResponseCacherTest.php` (extend existing) — with a `HeaderFilter` collaborator, stored response reflects filtered headers; without one (`null`, default), behavior is unchanged from before this issue.
- `source/tests/unit/lib/service/BackgroundRefresherTest.php` (extend existing) — same collaboration check on the refresh-write path.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` (extend existing) — `build()` reads all four new keys correctly, defaults applied when omitted.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` (extend existing) — end-to-end: a response with `Set-Cookie` gets a cache entry without it, using zero config (defaults) and using each new option; the `Response` returned to the caller (the live, non-cached path) still has all original headers, unfiltered.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php` (extend existing) — same `build()` coverage as `FileCacheMiddleware`.
- A staleness/background-refresh integration-style unit test confirming a refreshed write also has dangerous headers stripped (extend the existing `CacheStalenessMiddleware`/`BackgroundRefresher` test files rather than adding a new top-level file, unless the existing files don't have a natural seam for it).

## CI Checks

- `source/`: `composer coverage` (CI job: `unit_test`) — full PHPUnit suite with coverage.
- `source/`: `composer lint` (CI job: `checks`) — `phpcs`/`phpmd`, PSR-12.

Both commands run from inside `source/` (the CircleCI job flattens `source/source` and `source/tests` to the repo root before running them; locally, `cd source && composer coverage` / `cd source && composer lint` is the equivalent).

## Notes

- `DEFAULT_EXCLUDED_HEADERS` is placed on `ExcludedHeaderFilter` rather than `FileCacheMiddleware` (the issue's illustrative naming) specifically so `CacheStalenessMiddleware` doesn't need a dependency on `FileCacheMiddleware` to reach the same default — a deliberate, minor deviation from the issue text's example naming, not from its intent.
- Out of scope, per the issue: consolidating this with the existing `X-Cache-Time` read-path header injection in `FileCache::headers()`; renaming `skip_cache_header`/`require_cache_header`; any change to how headers are served on a cache read; retroactive cleanup/migration of cache entries written before this change ships.
- `docker_volumes/configuration/` (where real deployments configure rules) is not version-controlled and out of scope for this plan — no changes needed there.
