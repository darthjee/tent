# Tent Plan: ProcessingRequest: proxy-internal cache-skip flag honoured by FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce `RequestInterface::skipCache(): void` and `RequestInterface::cacheSkipped(): bool`, implemented by `ProcessingRequest` (one-way private boolean, default `false`, not in `ATTRIBUTES`) and `Request` (no-op / always `false`).
- With the flag set: `FileCacheMiddleware` neither reads, writes nor evicts; `CacheStalenessMiddleware::processRequest()` returns immediately. No header is ever added.
- Placement: full bypass only from `prependMiddlewares`; from `middlewares` only the write is bypassed. Pin both with handler tests.

## Steps

- [01 — Request models: the flag](tent/01-request-models.md)
- [02 — FileCacheMiddleware: honour the flag](tent/02-file-cache-middleware.md)
- [03 — CacheStalenessMiddleware: guard](tent/03-cache-staleness-middleware.md)
- [04 — Handler-level placement tests](tent/04-handler-placement-tests.md)

## CI Checks
- `source`: `docker compose run --rm tent_tests composer tests:unit` (CI job: unit tests in `.circleci/config.yml`)
- `source`: `docker compose run --rm tent_tests composer lint`

## Notes
- No new files under `source/source/`, so `source/source/loader.php` is unchanged. Step 04 adds one test-support middleware, which must be `require_once`d in `source/tests/support/loader.php`.
- Insert the flag check **first** in `shouldSkipCache()` — before the `skipCacheHeader === null` early return — otherwise a rule without `skip_cache_header` would ignore the flag.
- `CacheStalenessMiddleware` is currently never reached through a handler on a hit (darthjee/tent#311, out of scope). Test its guard at unit level only; do not try to fix the chain.
- Every new/extended test class uses `Logger::setInstance(new NullLoggerInstance())` in `setUp()` and restores `new LoggerInstance()` in `tearDown()`, matching existing specs.
- Keep docblock changes in the same commits as the code they document; the guide pages are product-dev-extending's work.
