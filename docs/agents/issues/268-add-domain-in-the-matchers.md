# Issue: Add domain in the matchers

## Description
The installation currently responds to two different domains, but `RequestMatcher` has no concept of domain at all — matchers only look at HTTP method and URI. `RequestInterface`/`Request` don't expose the request's Host either.

## Problem
There's no way to scope a matcher (and therefore a rule) to a specific domain. All matchers apply regardless of which domain the request came in on, so multi-domain installations can't have domain-specific routing rules.

## Expected Behavior
Configuration should accept an optional `domain` key on any matcher, alongside the existing `method`/`uri`/`type`:

```php
'matchers' => [
    ['uri' => '.json', 'type' => 'ends_with', 'domain' => 'mydomain.com'],
],
```

Wildcards using `%` should be supported with generic SQL `LIKE` semantics — matches any sequence of characters, anywhere in the pattern, any number of times:

```php
'matchers' => [
    ['uri' => '.json', 'type' => 'ends_with', 'domain' => '%.mydomain.com'],
],
```

A matcher with no `domain` set continues to match any domain, exactly like today (backward compatible).

## Solution
- Add a `domain()` accessor to `RequestInterface`/`Request`, backed by `$_SERVER['HTTP_HOST']`.
- Port is never part of the comparison: the `domain` config value is always a bare hostname (no `:port`), and any port present on the incoming `Host` header is stripped before comparing. `mydomain.com` matches the request regardless of which port it came in on.
- Domain comparison is case-insensitive.
- `domain` is a new matching criterion alongside `method` and `uri`, checked in the base `RequestMatcher` class the same way `requestMethod` already is — not a new matcher `type`. `matches()` becomes method AND uri AND domain, with `domain === null` meaning "any domain".
- Every existing `RequestMatcher` subclass's `build()` (`ExactRequestMatcher`, `BeginsWithRequestMatcher`, `EndsWithRequestMatcher`, `RegexRequestMatcher`) needs updating to accept and pass through the new `domain` param to the parent constructor. `RequestMethodMatcher` and `NegativeMatcher` are unrelated — they extend the separate `RequestResponseMatcher` hierarchy (used e.g. by `FileCacheMiddleware`), not `RequestMatcher`, and are out of scope here.
- The `%` wildcard is translated to a regex (escape the literal parts of the pattern, replace `%` with `.*`) for matching.
- Unit tests for domain matching belong in `source/tests/unit/lib/matchers/`, alongside each affected matcher's existing test suite, per the project's usual convention (`docs/adding-request-matchers.md`).
- Update `docs/adding-request-matchers.md`'s configuration example to show the new `domain` key.

## Benefits
Allows a single Tent installation serving multiple domains to define domain-specific routing rules, instead of every matcher applying indiscriminately across all domains.
