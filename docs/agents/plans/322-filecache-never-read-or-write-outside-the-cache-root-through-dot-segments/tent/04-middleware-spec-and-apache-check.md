# Middleware-level spec and Apache verification
Add a `FileCacheMiddleware` spec showing the end-to-end effect: a request whose path contains `..` goes through `processRequest()` without a response being set (no cache hit, even with a planted entry at the traversal target), and `processResponse()` with a cacheable 2xx stores nothing inside or outside the root. Model it on `FileCacheMiddlewareReservedPathTest.php`.

Then run the manual verification described in the issue's "Deployment verification": in the `tent` image, send `/../x`, `/a/../b`, `/a/./b`, `/a/%2e%2e/b`, `/a//b` and `/a%2fb` with `curl --path-as-is`, and record the HTTP status and the raw `requestPath()` tent saw (e.g. from the `warn`/debug log lines). Write the resulting table into the PR description; do not commit it. If running the image is not possible in the implementation environment, say so in the PR description and leave the table as the expected one from the issue, marked unverified.

## Files to Change
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareUnsafePathTest.php` — new.
