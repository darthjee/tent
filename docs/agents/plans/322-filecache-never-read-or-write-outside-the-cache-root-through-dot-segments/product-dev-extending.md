# Product-dev-extending Plan: FileCache: never read or write outside the cache root through dot segments

Main plan: [plan.md](plan.md)

## Shared contracts

- A request whose path or method has a `.` or `..` segment, or a NUL byte, is never cached: no cache read, no write, no directory created; the request is proxied to upstream unchanged and a `warn` line (`cache bypassed — ..., reason: unsafe path`) is logged.
- Cleanup targets (`CacheCleanupMiddleware`) with such a path are refused with a `warn` (`cache cleanup refused — path: ..., reason: unsafe path`) and delete nothing.
- Whole-segment match only: names such as `...`, `..foo`, `a..b` or `.git` are cached normally; tent does not URL-decode, so `%2e%2e` is a literal name.
- Trust boundary: the cache directory must be writable only by tent; symbolic links inside it are not defended against on the read/write path.

## Implementation Steps

### Step 1 — "Path safety" section in the cache guide
Add a short "Path safety" section to `docs/guides/tent/cache-configuration.md`, after "Bypass cache from a middleware". Cover, in a few sentences and a bullet list: which request paths are never cached and why (traversal outside the cache root), what happens to such a request (proxied uncached, logged), the same rule for cleanup targets, the names that stay cacheable, and the trust boundary above. Add one sentence saying that, in the shipped Apache image, dot segments in the raw request line can reach tent, which is why tent checks them itself. Do not include the detailed Apache table, which stays in the PR.

### Step 2 — Cross-reference from the cleanup middleware docs
In `docs/guides/tent/middlewares.md`, where #320 documented the `CacheCleanupMiddleware` path and symlink constraints, add one line linking to the new "Path safety" section and saying that unsafe cleanup paths are refused with a `warn`.

## Files to Change
- `docs/guides/tent/cache-configuration.md` — new "Path safety" section.
- `docs/guides/tent/middlewares.md` — one-line cross-reference in the `CacheCleanupMiddleware` notes.

## Notes
- Do not document `CacheDirResolver::resolveExact()` becoming `?string` in the guides; it is an internal class, and the change is called out in the PR description instead.
