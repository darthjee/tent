# Issue: Add Redirect Middleware

## Description

A new middleware is needed that, when configured on a route, performs an HTTP redirect. It should support regular expressions to rewrite the request path and return a 302 response with the new location.

## Problem

- There is currently no built-in middleware to redirect requests from one path to another.
- Without this middleware, redirect logic must be handled externally or duplicated in custom configurations.
- The existing request matchers (`exact`, `begins_with`, `ends_with`) only support fixed string comparisons; there is no way to match a route using a regular expression.

## Expected Behavior

- A new `RegexMatcher` is available that accepts a regular expression and matches any request path that satisfies it.
- The `RedirectMiddleware` accepts a regular expression pattern and a replacement string.
- When a request matches the pattern, the path is rewritten using the replacement.
- The middleware returns an HTTP 302 response with the `Location` header set to the rewritten URL.

## Solution

- Add a `RegexMatcher` class that implements the `RequestMatcher` interface and matches request paths against a regular expression.
- Create a `RedirectMiddleware` class that implements the middleware interface.
- Accept a `pattern` (regex) and `replacement` configuration option.
- On each request, apply the regex substitution to the request path.
- Short-circuit the request pipeline by returning a 302 response with the rewritten location.

## Benefits

- Enables simple redirect rules to be declared in Tent's configuration without additional backend logic.
- Supports dynamic path rewrites via regular expressions, covering a broad set of redirect scenarios.
- The new `RegexMatcher` is reusable beyond redirects — any route or middleware can use it for flexible path matching.

---
See issue for details: https://github.com/darthjee/tent/issues/214
