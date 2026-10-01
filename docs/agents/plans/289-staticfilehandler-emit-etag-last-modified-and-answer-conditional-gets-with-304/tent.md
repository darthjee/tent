# Tent Plan: StaticFileHandler: emit ETag / Last-Modified and answer conditional GETs with 304

Main plan: [plan.md](plan.md)

## Overview
`StaticFileHandler` gains a `conditional` option (default `false`). When enabled and the request method is `GET`, the handler:

1. Validates the path and checks the file exists (same as today, so `403` / `404` are unchanged).
2. Computes validators from `filesize` / `filemtime` only (no content read).
3. Returns a `304` response with `ETag` / `Last-Modified` and an empty body when the request's conditional headers match.
4. Otherwise returns the normal `200` with the validators added to the headers.

Because the `304` is returned from `processsRequest`, `RequestHandler::handleRequest` still applies the response middlewares (e.g. `SetHeadersMiddleware` for `Cache-Control`).

## Context
- `Tent\Content\File` (`source/source/lib/content/File.php`) emits only `Content-Type` / `Content-Length`. `contentLength()` calls `content()`, which reads the whole file, so the `304` path must not call `headers()`.
- `Tent\Service\ResponseContentReader` validates the path (`RequestPathValidator`) and existence, then builds a `Response`. It is shared with the cache (`ResponseContent` implementations), so leave its default behavior unchanged.
- Response headers are `"Name: value"` strings, emitted as-is by `source/source/index.php`. Request headers (`RequestInterface::headers()`) are an associative array whose key casing depends on the client and `getallheaders()`, so header lookup must be case-insensitive.
- Existing response subclasses to mirror: `MissingResponse`, `ForbiddenResponse` (`source/source/lib/models/`).

## Steps

- [01 — File validators](tent/01-file-validators.md)
- [02 — NotModifiedResponse and conditional matcher](tent/02-not-modified-response-and-matcher.md)
- [03 — Wire the option into StaticFileHandler](tent/03-static-file-handler-option.md)
- [04 — Document the option](tent/04-docs.md)

## CI Checks
- `source`: `docker compose run --rm tent_tests composer tests` (CI job: tests, `composer coverage`)
- `source`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)
- `source`: `docker compose run --rm tent_tests composer complexity` (PHPMD, keep the new methods small)

## Notes
- ETag format: strong, quoted, e.g. `"<md5(size . '-' . mtime)>"`. Weak comparison (strip `W/` on both sides) is used for `If-None-Match`, per RFC 9110.
- `If-Modified-Since` is only evaluated when `If-None-Match` is absent. An unparseable date is ignored (treated as no match). Compare with whole-second precision (`filemtime` is whole seconds).
- Use `clearstatcache()` (or `clearstatcache(true, $path)`) before reading `filesize` / `filemtime`, so files replaced in place inside a long-lived test process are seen fresh.
- Non-`GET` methods (including `HEAD`) and `conditional => false` must produce byte-identical responses to today's. Cover this with tests.
