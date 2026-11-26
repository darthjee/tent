# Tent Plan: CacheStalenessMiddleware: optionally evict the entry when a background refresh fails

Main plan: [plan.md](plan.md)

## Steps

- [01 — EvictionPolicy and loader](tent/01-eviction-policy.md)
- [02 — CacheStalenessMiddleware and BackgroundRefresher wiring](tent/02-staleness-wiring.md)
- [03 — FileCacheMiddleware read/write policy](tent/03-file-cache-middleware.md)
- [04 — Documentation](tent/04-documentation.md)

## CI Checks
- `source`: `docker compose run --rm tent_tests composer tests:unit` (CI job: unit tests in `.circleci/config.yml`)
- `source`: `docker compose run --rm tent_tests composer lint`

## Notes
- Suggested PR split (per the issue): PR 1 = steps 01, 02 and the staleness part of 04; PR 2 = step 03 and the `FileCacheMiddleware` part of 04. Docs go in separate commits from code and tests.
- `source/tests/unit/lib/service/BackgroundRefresherTest.php` already exists; extend it instead of creating it as the issue says.
- Defaults are `[]` for both middlewares (decided in the enhance pass), so tests for the `4xx` behaviour must configure `evictOn: ['4xx']` explicitly.
- Validation (non-array, or an entry that is neither an int nor a `HttpCodeMatcher`-style pattern) throws `\InvalidArgumentException` at build time.
- All new test classes call `Logger::setInstance(new NullLoggerInstance())` in `setUp()` and restore `new LoggerInstance()` in `tearDown()`.
