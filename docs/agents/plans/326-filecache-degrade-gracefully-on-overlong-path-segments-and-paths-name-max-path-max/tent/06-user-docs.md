# User docs

Document the new bypass next to the existing "Path safety" section of the cache configuration guide.

- Add a short **Path length** subsection (or a paragraph inside "Path safety"). It explains that a request is proxied upstream as usual, just without caching, when:
  - a request-path segment or the method is longer than 255 bytes;
  - or the cache file path built for it would exceed the filesystem path limit (4096 bytes, including the cache location).

  Lengths are counted in bytes on the raw, non-decoded path.
- Give the logged line: `cache bypassed — path: <path>, method: <method>, reason: path too long`, with the path truncated to 200 bytes followed by `… (<N> bytes)`.
- Mention that any other failure to create a cache directory (permissions, full or read-only disk) also only skips the store, logged as `cache store skipped — …`, and never affects the response.

## Files to Change
- `docs/guides/tent/cache-configuration.md` — new "Path length" content next to "Path safety".
