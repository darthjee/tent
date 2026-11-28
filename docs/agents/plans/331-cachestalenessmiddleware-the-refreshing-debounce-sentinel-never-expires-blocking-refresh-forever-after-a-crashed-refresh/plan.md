# Plan: CacheStalenessMiddleware: the `.refreshing` debounce sentinel never expires, blocking refresh forever after a crashed refresh

Issue: [331-cachestalenessmiddleware-the-refreshing-debounce-sentinel-never-expires-blocking-refresh-forever-after-a-crashed-refresh.md](../../issues/331-cachestalenessmiddleware-the-refreshing-debounce-sentinel-never-expires-blocking-refresh-forever-after-a-crashed-refresh.md)

## Overview
Give the `.refreshing` debounce sentinel of `CacheStalenessMiddleware` an expiry. When a stale hit finds a sentinel older than a TTL (new option `sentinel_ttl_seconds`, fixed default 60s, independent of `maxAgeSeconds`), the sentinel is treated as abandoned: it is overwritten, a refresh is scheduled and a `warn` is logged. The age comes from the sentinel's stored timestamp, with `filemtime()` as the fallback. The read is hardened with an `is_file()` guard and a 32-byte cap. The code builds on `main` after #311 (`8a2e0b5`). It must ship in the same release as #311, as a minor version bump.

## Agents involved

- [tent](tent.md): production code, unit and handler-level tests, `docs/guides/tent/` guides (as in #306).
- [product-dev-extending](product-dev-extending.md): the `CacheStalenessMiddleware` summary in `docs/creating-middlewares.md`.

## Shared contracts

The documented surface both agents must state identically:

- **Option:** `sentinel_ttl_seconds` (snake_case) or `sentinelTtlSeconds` (camelCase), an integer number of seconds. Cast to `int` when present; never throws.
- **Default:** `60` (`CacheStalenessMiddleware::DEFAULT_SENTINEL_TTL_SECONDS`, private), twice the refresh client's fixed 30s total cURL timeout. Not derived from `maxAgeSeconds`.
- **`<= 0`** (explicit only; a non-numeric value casts to `0`): the sentinel never expires (the pre-#331 behaviour).
- **Behaviour:** a sentinel older than the TTL is "abandoned". The next stale hit for that entry overwrites it, schedules the refresh and logs `warn` with the fixed prefix `[stale] - abandoned refresh sentinel taken over` (full line: `[stale] - abandoned refresh sentinel taken over — uri: <path>, age: <n>, sentinelTtlSeconds: <ttl>`). A sentinel within the TTL keeps debouncing with the existing `debug` line `[stale] - refresh already in progress, skipping — uri: <path>`.
- **Constructor:** `?int $sentinelTtlSeconds = null` is the 11th parameter, after `?array $evictOn = null`.
- **Callout for docs:** abandoned refresh sentinels are now taken over after 60s (logged at `warn`); set `sentinel_ttl_seconds: 0` to restore the previous never-expire behaviour.
- **Out of scope:** the race where a slow refresh's `finally` deletes the takeover's sentinel is tracked in #333. Docs must not claim a strictly single refresh.
