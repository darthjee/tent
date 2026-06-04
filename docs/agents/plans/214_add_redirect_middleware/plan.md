# Plan: Add Redirect Middleware

## Overview

Implement two new components:
1. `RegexMatcher` — a request matcher that matches route paths against a regular expression.
2. `RedirectMiddleware` — a middleware that rewrites the request path using a regex pattern/replacement and returns an HTTP 302 response.

## Context

The existing matchers (`exact`, `begins_with`, `ends_with`) only support fixed string comparisons. To support flexible redirect rules, a regex-based matcher is needed. The `RedirectMiddleware` will use this capability to rewrite paths and short-circuit the request pipeline with a redirect response.

## Implementation Steps

### Step 1 — Add `RegexMatcher`

Create `source/source/lib/matchers/RegexRequestMatcher.php`.

- Implements the `RequestMatcher` interface.
- Accepts a `pattern` configuration key (a regular expression string).
- `match(ProcessingRequest $request)` returns `true` if the request URI matches the pattern.
- Register it in `source/source/loader.php`.
- Add unit tests under `source/tests/unit/matchers/`.

### Step 2 — Add `RedirectMiddleware`

Create `source/source/lib/middlewares/RedirectMiddleware.php`.

- Implements the middleware interface (`processRequest`).
- Accepts `pattern` (regex) and `replacement` configuration keys.
- On `processRequest`: applies `preg_replace($pattern, $replacement, $uri)` to the request URI, then short-circuits the pipeline by returning a 302 response with a `Location` header set to the rewritten URI.
- Register it in `source/source/loader.php`.
- Add unit tests under `source/tests/unit/middlewares/`.

### Step 3 — Integration tests

Add integration tests that configure a rule using `RegexMatcher` + `RedirectMiddleware` and verify:
- A matching request receives a 302 with the correct `Location` header.
- A non-matching request is not redirected.

### Step 4 — Documentation

Update `docs/agents/architecture.md` to list the new matcher and middleware in their respective tables.

## Files to Change

- `source/source/lib/matchers/RegexRequestMatcher.php` — new class
- `source/source/lib/middlewares/RedirectMiddleware.php` — new class
- `source/source/loader.php` — add `require_once` for both new classes
- `source/tests/unit/matchers/RegexRequestMatcherTest.php` — unit tests
- `source/tests/unit/middlewares/RedirectMiddlewareTest.php` — unit tests
- `source/tests/integration/` — integration test for redirect flow
- `docs/agents/architecture.md` — add `RegexRequestMatcher` to the matchers table and `RedirectMiddleware` to the middlewares table
- `docs/HOW_TO_USE_DARTHJEE-TENT.md` — add `regex` to the matcher types table; add a `RedirectMiddleware` section under Middlewares; update the Reference tables for middlewares and rule matchers

## Notes

- The `RedirectMiddleware` should short-circuit the pipeline at the `processRequest` phase, so the handler and downstream middlewares are never invoked.
- The regex `pattern` should be passed as a full PHP regex string (including delimiters, e.g. `'/^\/old(.*)$/i'`), consistent with how PHP's `preg_match` / `preg_replace` work.
- The `RegexMatcher` is general-purpose and may be used independently of `RedirectMiddleware` in any route configuration.
