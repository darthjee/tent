# Product-dev-extending Plan: CacheStalenessMiddleware: the `.refreshing` debounce sentinel never expires, blocking refresh forever after a crashed refresh

Main plan: [plan.md](plan.md)

## Shared contracts

This agent **relies on** `tent` producing the contract in [plan.md](plan.md#shared-contracts):

- option `sentinel_ttl_seconds` / `sentinelTtlSeconds`, an integer number of seconds;
- default `60`, independent of `maxAgeSeconds`;
- an explicit `<= 0` (or a non-numeric value) means never expires;
- an abandoned sentinel is taken over on the next stale hit, with a `warn`.

Verify all of these against the real `CacheStalenessMiddleware::build()` and the constructor once `tent` has landed. Do not just restate them.

## Implementation Steps

### Step 1 — Update the `CacheStalenessMiddleware` summary in `docs/creating-middlewares.md`
In the bullet list of the `CacheStalenessMiddleware` section (around the existing `<meta-file>.refreshing` bullet):

- Add a bullet for `sentinel_ttl_seconds` (or `sentinelTtlSeconds`): optional, default `60` seconds, not tied to `maxAgeSeconds`; `0` or negative means the sentinel never expires.
- Extend the sentinel bullet: a sentinel older than that TTL (e.g. left behind by a refresh that crashed) is taken over by the next stale hit, with a warning logged. It stays best effort.
- Link to `guides/tent/middlewares.md#cachestalenessmiddleware` for details instead of duplicating them.

Then prove the documentation is truthful. Read `build()` and the constructor in `source/source/lib/middlewares/CacheStalenessMiddleware.php` and check the key names, the casting and the default. Report any mismatch to `tent` rather than documenting around it.

## Files to Change
- `docs/creating-middlewares.md` — `CacheStalenessMiddleware` option and sentinel bullets.

## Notes
- Documentation-only, in its own commit, after `tent`'s code steps.
- Do not touch `docs/guides/tent/`: `tent`'s step 04 owns it for this issue, as in #306.
