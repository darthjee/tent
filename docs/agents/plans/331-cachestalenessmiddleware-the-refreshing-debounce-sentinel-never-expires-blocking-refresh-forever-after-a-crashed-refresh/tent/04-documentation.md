# Documentation in `docs/guides/tent/`

Separate commit from code and tests.

- `docs/guides/tent/middlewares.md`, §`CacheStalenessMiddleware`:
  - **Options:** add `sentinelTtlSeconds` (or `sentinel_ttl_seconds`). Default `60` seconds, not related to `maxAgeSeconds`, chosen as twice the refresh's 30s total timeout. An explicit `0` or negative value, or a non-numeric one, means the sentinel never expires.
  - **When a refresh happens:** extend the sentinel bullet. A sentinel older than the TTL is considered abandoned; the next stale hit takes it over (overwrites it and refreshes) and logs a `warn`. Show the line in a `text` block: `[stale] - abandoned refresh sentinel taken over — uri: <request path>, age: <seconds>, sentinelTtlSeconds: <ttl>`. Keep "best effort": two simultaneous takeovers, or a slow refresh outliving the TTL, can cause a duplicate refresh (see #333).
  - **Timing:** replace the bullet "If PHP dies during a refresh ... not refreshed again until that file or the entry's directory is deleted" with the new behaviour: recovered automatically after the TTL on the next stale hit, unless the TTL is `<= 0`.
  - Add the behaviour-change callout from the shared contract (a short note under Options is fine).
- `docs/guides/tent/cache-configuration.md`: where `CacheStalenessMiddleware` options are cross-referenced, mention `sentinel_ttl_seconds` and link to the middlewares guide. Do not duplicate the full explanation.
- The class docblock is updated in step 01; keep its wording consistent with the guide.

## Files to Change
- `docs/guides/tent/middlewares.md` — options, "When a refresh happens", "Timing", callout.
- `docs/guides/tent/cache-configuration.md` — cross-reference to the new option.
