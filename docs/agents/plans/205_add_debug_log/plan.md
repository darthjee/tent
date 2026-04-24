# Plan: Add Debug Log

## Overview

Add `Logger::debug()` calls to every code path in `source/` that can produce a 404 response, so that when `LOG_LEVEL=debug` developers can see exactly why a request returned 404. Update the agent documentation accordingly.

## Context

The `Logger` static facade is already in place (`Logger::debug()`, `Logger::info()`, etc.) and reads `LOG_LEVEL` from the environment. No new infrastructure is needed — only log statements and tests.

There are four distinct 404 code paths identified:

1. **No rules matched** — `RequestProcessor` falls back to `MissingRequestHandler`
2. **Static file not found or path is a directory** — `StaticFileHandler` catches `FileNotFoundException`
3. **Proxied upstream returned 404** — proxy handler passes through the upstream 404 response
4. **Cached 404 replayed** — `FileCacheMiddleware` serves a previously cached 404

## Implementation Steps

### Step 1 — Log when no rules matched

In `RequestProcessor.php`, before delegating to `MissingRequestHandler`, add:

```
404: no rules matched — method: <METHOD>, uri: <URI>
```

Example: `404: no rules matched — method: GET, uri: /api/users`

### Step 2 — Log when a static file is not found

In `StaticFileHandler.php`, inside the `FileNotFoundException` catch block, add:

```
404: static file not found — uri: <URI>, resolved path: <ABSOLUTE_PATH>
```

Example: `404: static file not found — uri: /assets/logo.png, resolved path: /var/www/static/assets/logo.png`

### Step 3 — Log when the proxied upstream returns 404

In the proxy handler(s) (`DefaultProxyRequestHandler` and/or `ProxyRequestHandler`), after receiving the upstream response, add:

```
404: upstream returned 404 — method: <METHOD>, uri: <URI>, upstream: <UPSTREAM_URL>
```

Example: `404: upstream returned 404 — method: GET, uri: /api/users, upstream: http://api:80/api/users`

The exact location depends on where the upstream response is first available — to be confirmed by code inspection.

### Step 4 — Log when a cached 404 is served

In `FileCacheMiddleware.php`, when the cached response is about to be returned and its status code is 404, add:

```
404: serving cached response — uri: <URI>
```

Example: `404: serving cached response — uri: /api/users`

### Step 5 — Add tests

For each code path above, add or extend the corresponding PHPUnit test to assert that `Logger::debug()` is called with the expected message when a 404 occurs. Use `NullLoggerInstance` as the test double (already exists) or a mock that captures calls.

### Step 6 — Update documentation

Update `docs/agents/architecture.md` under the Logger section to describe the new debug log messages and when each is emitted.

## Files to Change

- `source/source/lib/service/RequestProcessor.php` — add debug log before fallback to `MissingRequestHandler`
- `source/source/lib/request_handlers/StaticFileHandler.php` — add debug log in `FileNotFoundException` catch block
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` and/or `ProxyRequestHandler.php` — add debug log when upstream returns 404
- `source/source/lib/middlewares/FileCacheMiddleware.php` — add debug log when replaying a cached 404
- `source/tests/unit/` — extend or add unit tests for each code path
- `docs/agents/architecture.md` — document the new debug log messages

## Notes

- The exact hook point for the proxy 404 (Step 3) needs to be confirmed by reading the proxy handler code — it may live in a base class (`RequestHandler.php`) rather than in each concrete handler.
- `NullLoggerInstance` silences all logs in tests; if asserting log calls is needed, a spy/mock may be required. Check existing test patterns first.
- No changes to `loader.php` are needed since no new classes are added.
