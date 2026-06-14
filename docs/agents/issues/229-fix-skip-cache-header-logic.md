# Issue: Fix skip_cache_header Logic

## Description

The `skip_cache_header` option for the proxy (which skips the cache when a specific header is present) is currently broken in multiple ways.

## Problem

- In `source/lib/middlewares/FileCacheMiddleware.php`, the code checks the **request** header to decide whether to use the cache, but it should be checking the **response** header to decide whether to **save** the response into the cache.
- The system should also skip **reading** from the cache when the incoming request contains the configured skip header.

## Expected Behavior

- When the response contains the configured `skip_cache_header`, the response should not be saved to the cache.
- When the request contains the configured `skip_cache_header`, the cached response should not be used and a fresh request should be made.

## Solution

- Fix `FileCacheMiddleware.php` to check the response headers when determining whether to persist a response to cache.
- Add logic to check the request headers and bypass cache reads when the skip header is present.

## Benefits

- Ensures the `skip_cache_header` option works correctly for both reading and writing the cache.
- Allows clients to force fresh responses via a header without storing uncacheable responses.

---
See issue for details: https://github.com/darthjee/tent/issues/229
