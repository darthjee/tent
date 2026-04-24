# Issue: Add Debug Log

## Description

When the log level is set to `debug`, Tent should log the specific reason why a 404 response is being returned for requests handled by the source project. This gives developers visibility into why requests are failing.

## Problem

- When a 404 is returned, it is currently unclear what caused it
- No debug-level logging exists to distinguish between different failure modes

## Expected Behavior

- When log level is `debug`, Tent logs the root cause of every 404 response. The known causes are:
  - **No rules matched the request** — `RequestProcessor` falls back to `MissingRequestHandler`
    (`source/lib/service/RequestProcessor.php` lines 69–79 and `source/lib/request_handlers/MissingRequestHandler.php` line 25)
  - **A proxied request returned 404 from the upstream** — proxy handler passes through the upstream 404
  - **A static file was not found or the path is a directory** — `StaticFileHandler` catches `FileNotFoundException` and returns a `MissingResponse`
    (`source/lib/request_handlers/StaticFileHandler.php` line 109)
  - **A previously cached 404 is being served** — `FileCacheMiddleware` replays a cached 404 response from a prior request
    (`source/lib/middlewares/FileCacheMiddleware.php` lines 113–116)

## Solution

- Add a debug log statement in each of the four code paths above with a descriptive message
- Update `docs/agents/` documentation to describe the new debug logging behavior

## Benefits

- Easier debugging of routing and proxy issues
- Faster diagnosis of missing static files or unmatched rules

---
See issue for details: https://github.com/darthjee/tent/issues/205
