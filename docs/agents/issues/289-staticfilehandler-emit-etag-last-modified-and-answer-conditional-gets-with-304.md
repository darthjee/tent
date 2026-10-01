# Issue: StaticFileHandler: emit ETag / Last-Modified and answer conditional GETs with 304

## Description
Majora (darthjee/majora#1471, part of darthjee/majora#1468) needs to serve user photos with `Cache-Control: no-cache` and have clients revalidate them cheaply. Photos are replaced in place (same path), so a changed file must show up on the next view, while an unchanged file should cost only a `304` round-trip.

## Problem
Today `StaticFileHandler` (via `Tent\Content\File::headers()`) emits only `Content-Type` and `Content-Length`. It sets no validators and never answers conditional requests, so every revalidation re-downloads the full file.

## Expected Behavior
The feature is **opt-in** per rule via a handler option `'conditional' => true`. Without it (the default), the static handler behaves exactly as today: no new headers, no `304`.

When enabled, for a `200` from the `static` handler on a `GET` request:

- An `ETag` header derived from the file on disk (a quoted hash of size + mtime — cheap, and changes whenever the file is overwritten).
- A `Last-Modified` header with the file's mtime in IMF-fixdate format (`D, d M Y H:i:s \G\M\T`).

Conditional requests (`GET` only; `HEAD` and other methods are unchanged):

- If `If-None-Match` matches the current ETag (supporting `*`, comma-separated lists, and weak `W/` comparison), respond `304 Not Modified`.
- Otherwise, if `If-None-Match` is absent and `If-Modified-Since` is at or after the file's mtime, respond `304 Not Modified`.
- A `304` has an empty body, keeps `ETag` / `Last-Modified`, and drops `Content-Length` / `Content-Type`. It is answered without reading the file contents.
- `404` / `403` responses are unchanged.
- Rule-level middlewares (e.g. a `Cache-Control` setter via `SetHeadersMiddleware`) still run on the `304` response.

## Solution
- `StaticFileHandler::build` reads a new `conditional` option (default `false`) and stores it on the handler.
- `File` exposes the validators (ETag, Last-Modified) computed from `filesize`/`filemtime` so they can be added to the response headers when the option is enabled.
- When enabled and the request is a `GET`, `StaticFileHandler::processsRequest` evaluates the conditional headers after path validation / existence checks and returns a `304` response (no body read) when they match; otherwise it falls through to the existing `ResponseContentReader` flow.
- Since the 304 is returned from `processsRequest`, the handler's response middlewares run on it unchanged.
- Document the new option in the handler's docblock and in the configuration docs.
- PHPUnit coverage (option on and off, GET vs. other methods) under `source/tests/unit/lib/request_handlers/StaticFileHandler/`.

## Benefits
- Clients can use `no-cache` and still get cheap revalidation (`304` with no body).
- Files replaced in place are picked up on the next request.
- Once released, Majora bumps its `darthjee/tent` image tag and enables `'conditional' => true` and `no-cache` on `/photos` and `/files`.
