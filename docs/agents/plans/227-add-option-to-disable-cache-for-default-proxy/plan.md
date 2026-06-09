# Plan: Add Option to Disable Cache for Default Proxy

## Overview

The `'cache' => false` option already exists in `DefaultProxyRequestHandler` (since PR #172). The implementation is complete and documented. What is missing is test coverage for the `::build()` code path with `'cache' => false`.

## Context

`DefaultProxyRequestHandler` auto-adds `FileCacheMiddleware` unless `cache` is set to `false`. The `build()` method already handles this:

```php
$cache = array_key_exists('cache', $params) ? $params['cache'] : './cache';
```

Existing no-cache unit tests (`DefaultProxyRequestHandlerNoCacheTest`) only instantiate the handler via the constructor — they do not exercise `::build()`. This leaves the configuration path untested.

## Implementation Steps

### Step 1 — Add `DefaultProxyRequestHandlerBuildTest`

Create `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` with tests covering:

- `build()` without `cache` key → handler uses default cache path (`'./cache'`), `FileCacheMiddleware` is present
- `build(['cache' => false])` → `FileCacheMiddleware` is absent; HTTP client is called (not cache-short-circuited)
- `build(['cache' => '/custom/path'])` → handler uses custom cache path

## Files to Change

- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — new test file covering `::build()` with and without `'cache' => false`

## Notes

- No changes to production code are expected — the feature is already implemented.
- To assert whether `FileCacheMiddleware` is in the stack, use reflection or a request that would be served from cache (pre-warm the cache, call the handler, assert the HTTP client is NOT called when cache is active and IS called when cache is disabled).

## CI Checks

Before opening a PR, run the following checks for the folders being modified:

- `source/`: `docker compose run --rm tent_tests composer tests:unit` (CircleCI job: `unit_test`)
- `source/`: `docker compose run --rm tent_tests composer lint` (CircleCI job: `checks`)
