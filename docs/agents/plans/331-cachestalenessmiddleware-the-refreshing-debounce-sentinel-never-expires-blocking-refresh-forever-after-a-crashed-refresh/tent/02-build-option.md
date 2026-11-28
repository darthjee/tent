# `build()` option and constructor wiring

Expose the TTL through rule configuration.

- In `CacheStalenessMiddleware::build()`:
  - Read `$sentinelTtl = $attributes['sentinel_ttl_seconds'] ?? $attributes['sentinelTtlSeconds'] ?? null;` and compute `$sentinelTtlSeconds = $sentinelTtl === null ? null : (int) $sentinelTtl;`. Do **not** write `(int) (... ?? null)`: that would turn "absent" into `0`, which means never expires.
  - Pass it with the named argument: `new self($location, $maxAgeSeconds, $host, evictOn: $evictOn, sentinelTtlSeconds: $sentinelTtlSeconds)`.
  - Do not touch the deprecated header-filter attributes or `warnDeprecatedAttributes()`.
- Update the `build()` docblock: `sentinel_ttl_seconds` / `sentinelTtlSeconds`, the 60s default (independent of `maxAgeSeconds`), and that an explicit `<= 0` or a non-numeric value means never expires.

**Tests**, in `CacheStalenessMiddlewareBuildTest.php`, same commit. Assert through behaviour, not by reflecting into the property: build the middleware, inject a recording scheduler the way the file already does, and write a sentinel next to the entry.

- `sentinel_ttl_seconds: 300` is honoured: a sentinel aged 200s debounces and one aged 400s is taken over. `sentinelTtlSeconds` works the same way.
- Absent: the TTL is 60 whatever `maxAgeSeconds` is. With `maxAgeSeconds: 300`, 90s is taken over and 30s debounces.
- `maxAgeSeconds: 0` with no TTL: 90s is still taken over. This is the regression guard for "always refresh".
- `sentinel_ttl_seconds: 'abc'` casts to `0` without throwing, and an 86400s-old sentinel debounces.

## Files to Change
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — `build()` and its docblock.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php` — the four cases above.
