# Plan: Add domain in the matchers

Issue: [268-add-domain-in-the-matchers.md](../../issues/268-add-domain-in-the-matchers.md)

## Overview

Add an optional `domain` criterion to `RequestMatcher` (the URI-matching hierarchy: `ExactRequestMatcher`, `BeginsWithRequestMatcher`, `EndsWithRequestMatcher`, `RegexRequestMatcher`), checked alongside the existing `method`/`uri` criteria. The domain comes from a new `domain()` accessor on `Request`/`RequestInterface`, sourced from the `Host` header with the port stripped, and supports `%`-wildcards with SQL `LIKE` semantics. Fully backward compatible: matchers without `domain` keep matching any domain.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **Config key**: `domain` (optional string) is accepted in the same associative array passed to `RequestMatcher::build()`/`buildMatchers()`, alongside `method`, `uri`, `type` (and `pattern` for regex). Example:
  ```php
  ['uri' => '.json', 'type' => 'ends_with', 'domain' => 'mydomain.com']
  ```
- **Wildcard**: `%` matches any sequence of characters (including empty), can appear anywhere in the pattern, any number of times — generic SQL `LIKE` semantics. Example: `'%.mydomain.com'`, `'%.mydomain.%'`.
- **Port handling**: `domain` config values never include a port. Whatever port is present on the actual request's `Host` header is stripped before comparing, so `'mydomain.com'` matches regardless of the port the request came in on.
- **Case-insensitivity**: domain comparison ignores case.
- **No domain specified**: a matcher with no `domain` set matches any domain (same as `method`/`uri` being `null` today).
- `product-dev-extending`'s doc example in `docs/adding-request-matchers.md` must use exactly this `domain` key and `%` wildcard syntax, matching `tent`'s implementation.
