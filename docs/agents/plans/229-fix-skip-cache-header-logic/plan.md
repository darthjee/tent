# Plan: Fix skip_cache_header Logic

## Overview

Fix two bugs in `FileCacheMiddleware` related to the `skip_cache_header` option:

1. `processResponse` currently checks the **request** headers to decide whether to skip saving a response to cache. It should check the **response** headers instead.
2. `processRequest` correctly skips reading from cache when the request contains the configured skip header — this behavior must be preserved.

## Context

`FileCacheMiddleware` supports a `skip_cache_header` option. When a header with that name is present, the cache should be bypassed. There are two distinct bypass scenarios:

- **Read bypass** (`processRequest`): if the incoming request carries the skip header, serve the response fresh from the upstream instead of from cache.
- **Write bypass** (`processResponse`): if the upstream response carries the skip header, do not persist it to disk cache.

Currently both paths check the **request** headers, meaning the write bypass is triggered by the wrong source (request instead of response).

## Implementation Steps

### Step 1 — Fix `processResponse` to check response headers

In `FileCacheMiddleware::processResponse`, replace the call to `shouldSkipCache($response->request())` with a new private method `shouldSkipCacheForResponse(Response $response)` that reads from `$response->headers()` instead of the request's headers.

Keep `shouldSkipCache(RequestInterface $request)` unchanged for use in `processRequest`.

### Step 2 — Update unit tests for `processResponse`

The existing tests `testProcessResponseSkipsCacheWriteWhenSkipCacheHeaderIsPresent` and `testProcessResponseSkipsCacheWriteCaseInsensitively` pass the skip header on the **request**, which was testing the broken behavior. Update them to pass the header on the **response** instead.

Add a new test to confirm that the skip header on the **request** alone does **not** prevent a cache write (i.e., request-side header only affects read, not write).

### Step 3 — Verify `processRequest` tests still pass

The tests `testProcessRequestSkipsCacheReadWhenSkipCacheHeaderIsPresent` and `testProcessRequestSkipsCacheReadCaseInsensitively` should continue to pass unchanged, confirming the read-bypass path is unaffected.

## Files to Change

- `source/source/lib/middlewares/FileCacheMiddleware.php` — add `shouldSkipCacheForResponse(Response $response)` method; update `processResponse` to call it
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` — fix existing skip-header tests; add test for request-header-only case

## Notes

- The `Response` class must expose a `headers()` method (needs verification) to support reading response headers in the new private method.
- No changes expected to `processRequest`, `shouldSkipCache`, or the read-path tests.
- No changes to the `build()` method or configuration parsing — `skip_cache_header` is already parsed correctly.
