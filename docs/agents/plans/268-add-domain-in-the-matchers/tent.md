# Tent Plan: Add domain in the matchers

Main plan: [plan.md](plan.md)

## Shared contracts

- Accept an optional `domain` key (string) in the params array passed to `RequestMatcher::build()` and each concrete subclass's `build()`, alongside `method`/`uri`/`type`/`pattern`.
- `%` is the wildcard character with SQL `LIKE` semantics: matches any sequence of characters (incl. empty), anywhere in the pattern, any number of times.
- `domain` config values are always a bare hostname, no port. Strip any port from the actual request's `Host` header before comparing.
- Comparison is case-insensitive.
- `domain === null` (not set) matches any domain — must not change behavior for existing configs that don't use it.

## Implementation Steps

### Step 1 — Add `domain()` to `RequestInterface` and `Request`

- Add `domain(): string` (or `?string`, if a request could plausibly have no `Host` at all — check how `requestPath()`/`requestUri()` handle the missing-value case and mirror it) to `source/source/lib/models/RequestInterface.php`.
- Implement it in `source/source/lib/models/Request.php`, following the existing pattern (`$this->options['domain'] ?? $this->get(...)`). Source the value from `$_SERVER['HTTP_HOST']`. Add `'HTTP_HOST' => 'host'` (or similar) handling to the private `get()` switch, the same way `'uri'`/`'request_method'` are handled today.
- Do **not** strip the port here — `domain()` should return the raw `Host` header value (e.g. `mydomain.com:8080`). Port stripping is the matcher's job (Step 2), since the matcher is what knows whether the configured pattern cares about a port at all.

### Step 2 — Add domain matching to the base `RequestMatcher`

In `source/source/lib/matchers/RequestMatcher.php`:

- Add a `protected $requestDomain` property, set via a new optional constructor parameter (after `$requestUri`, defaulting to `null`) — mirror how `$requestMethod`/`$requestUri` are already handled.
- Extend `matches()` to also require `matchRequestDomain($request)`: `matchRequestMethod && matchRequestUri && matchRequestDomain`.
- Add a `private function matchRequestDomain(RequestInterface $request): bool`:
  - Return `true` if `$this->requestDomain === null`.
  - Otherwise, strip any `:port` suffix from `$request->domain()`, then compare case-insensitively against `$this->requestDomain` using the `%`-wildcard semantics from "Shared contracts" above (e.g. escape the pattern for regex, replace `%` with `.*`, anchor start/end, use a case-insensitive match — or use `fnmatch()` with `%` translated to `*` and the `FNM_CASEFOLD` flag, whichever reads cleaner against the existing code style).

### Step 3 — Wire `domain` through `build()`

Update `RequestMatcher::build()`'s subclasses to read and pass through the `domain` param:

- `ExactRequestMatcher::build()`
- `BeginsWithRequestMatcher::build()`
- `EndsWithRequestMatcher::build()`
- `RegexRequestMatcher::build()`

Each becomes `new self($params['method'] ?? null, $params['uri'] ?? null, $params['domain'] ?? null)` (adjust for `RegexRequestMatcher`'s `$pattern` positional argument).

Do **not** touch `RequestMethodMatcher` or `NegativeMatcher` — they extend the separate `RequestResponseMatcher` hierarchy (used by `FileCacheMiddleware`), which is out of scope for this issue.

### Step 4 — Unit tests

- `source/tests/unit/lib/matchers/RequestMatcher/RequestMatcherGeneralTest.php` (or a new sibling file, e.g. `RequestMatcherDomainTest.php`, if that reads cleaner): cover domain matching against a mocked `Request` — exact domain match, non-matching domain, wildcard matches (leading `%.`, trailing `%`, mid-pattern `%`, multiple `%`), case-insensitivity, port stripping (`Host` with a port still matches a portless configured `domain`), and `domain === null` matching any domain.
- `source/tests/unit/lib/matchers/RequestMatcher/RequestMatcherBuildTest.php`: cover `RequestMatcher::build()`/`buildMatchers()` correctly passing `domain` through to each matcher subclass.
- `source/tests/unit/lib/models/RequestTest.php`: add coverage for `domain()` reading from the `Host` header / `options` override, following the existing test pattern for `requestMethod()`/`requestPath()`.

## Files to Change

- `source/source/lib/models/RequestInterface.php` — add `domain()` to the interface.
- `source/source/lib/models/Request.php` — implement `domain()`.
- `source/source/lib/matchers/RequestMatcher.php` — add `$requestDomain`, `matchRequestDomain()`, wire into `matches()`.
- `source/source/lib/matchers/ExactRequestMatcher.php` — pass `domain` through `build()`.
- `source/source/lib/matchers/BeginsWithRequestMatcher.php` — pass `domain` through `build()`.
- `source/source/lib/matchers/EndsWithRequestMatcher.php` — pass `domain` through `build()`.
- `source/source/lib/matchers/RegexRequestMatcher.php` — pass `domain` through `build()`.
- `source/tests/unit/lib/matchers/RequestMatcher/RequestMatcherGeneralTest.php` (or new file) — domain matching tests.
- `source/tests/unit/lib/matchers/RequestMatcher/RequestMatcherBuildTest.php` — `domain` pass-through tests.
- `source/tests/unit/lib/models/RequestTest.php` — `domain()` accessor tests.

## CI Checks

- `source`: `docker compose run --rm tent_tests composer tests` (CI job: `unit_test`)
- `source`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)

## Notes

- `loader.php` (`source/source/loader.php`) does not need changes — no new files are being added, only existing classes are modified.
- Keep `RequestMatcher`'s public API additive: existing callers constructing matchers positionally with just `($method, $uri)` must keep working since the new parameter is optional and appended at the end.
