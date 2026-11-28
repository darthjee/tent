# Tent Plan: CacheStalenessMiddleware never runs on a cache hit: the request-middleware chain stops at FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

Produces everything listed in [plan.md § Shared contracts](plan.md#shared-contracts): `CacheHit`, `ProcessingRequest::setCacheHit()`/`cacheHit()`, the `FileCacheMiddleware` marker write, and the response-phase `CacheStalenessMiddleware` with deprecated header-filter options.

## Steps

- [01 — Add CacheHit marker](tent/01-add-cache-hit-marker.md)
- [02 — Set the marker in FileCacheMiddleware](tent/02-set-marker-in-file-cache-middleware.md)
- [03 — Move staleness check to the response phase](tent/03-response-phase-staleness.md)
- [04 — Handler-level tests](tent/04-handler-level-tests.md)

## CI Checks

- `source`: `docker compose run --rm tent_tests composer tests` (CI job: coverage)
- `source`: `docker compose run --rm tent_tests composer lint` (CI job: lint)

## Notes

- Register `CacheHit.php` in `source/source/loader.php` after `FileCache.php` and the `HeaderFilter` file, before `ProcessingRequest.php`.
- Keep `RequestHandler` and `Middleware` untouched; the request-phase short-circuit must not change.
