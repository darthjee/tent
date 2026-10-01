# NotModifiedResponse and conditional matcher
Add the building blocks for the 304 path.

1. `Tent\Models\NotModifiedResponse extends Response`: constructor `(RequestInterface $request, array $headers)`, with `httpCode` 304, body `''`, and the headers given (only `ETag` / `Last-Modified`, never `Content-Type` / `Content-Length`). Mirror `MissingResponse`.
2. A small service, e.g. `Tent\Service\ConditionalRequestMatcher`, built from `(RequestInterface $request, string $etag, int $lastModified)` with `isNotModified(): bool`:
   - Look up request headers case-insensitively.
   - If `If-None-Match` is present: return true when it is `*`, or when any comma-separated entry equals the ETag under weak comparison (strip a leading `W/` and surrounding whitespace on both sides). Do **not** consult `If-Modified-Since` in this case.
   - Else if `If-Modified-Since` is present and parses via `strtotime` (false means ignore it): return true when the parsed timestamp is `>= $lastModified`.
   - Otherwise return false.

## Files to Change
- `source/source/lib/models/NotModifiedResponse.php`: new.
- `source/source/lib/service/ConditionalRequestMatcher.php`: new. Check the folder/namespace casing against `ResponseContentReader`, and the class map/autoload as described in `docs/agents/architecture.md`.
- `source/tests/unit/lib/models/NotModifiedResponseTest.php`: new.
- `source/tests/unit/lib/service/ConditionalRequestMatcherTest.php`: new. Cover exact match, list match, `*`, `W/` on either side, mismatch, `If-None-Match` mismatch with a matching `If-Modified-Since` (expect false), IMS equal/after/before mtime, invalid IMS date, mixed-case header names, and no headers.
