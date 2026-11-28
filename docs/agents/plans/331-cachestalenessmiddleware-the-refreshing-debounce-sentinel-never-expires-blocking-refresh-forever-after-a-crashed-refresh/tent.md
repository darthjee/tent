# Tent Plan: CacheStalenessMiddleware: the `.refreshing` debounce sentinel never expires, blocking refresh forever after a crashed refresh

Main plan: [plan.md](plan.md)

## Shared contracts

This agent **produces** the full contract in [plan.md](plan.md#shared-contracts): the option names and casting, the 60s default constant, the `<= 0` escape hatch, the 11th constructor parameter, the exact `warn`/`debug` lines, and the docs callout text. `product-dev-extending` documents that contract, so any deviation here must be reported back.

## Steps

- [01 — Sentinel expiry and hardened read](tent/01-sentinel-expiry.md)
- [02 — `build()` option and constructor wiring](tent/02-build-option.md)
- [03 — Handler-level regression tests](tent/03-handler-regression.md)
- [04 — Documentation in `docs/guides/tent/`](tent/04-documentation.md)

## CI Checks
- `source/`: `docker compose run --rm tent_tests composer tests` (CI job: `unit_test`, runs `composer coverage`)
- `source/`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)
- Focused run: `docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/lib/middlewares/CacheStalenessMiddleware tests/unit/lib/request_handlers/ProxyRequestHandler`

## Notes
- Commit hygiene (`docs/agents/contributing.md`): steps 01–03 put tests and implementation in the same commit; step 04 (docs) is a separate commit.
- Production code touches only `source/source/lib/middlewares/CacheStalenessMiddleware.php`. There is no new class and no `loader.php` change; `FileCache`, `BackgroundRefresher` and the scheduler stay untouched.
- Do not reintroduce `@file_put_contents()`: the takeover reuses `writeSentinel()` (`FileUtils::writeAtomically()`) unchanged.
- The `is_file()` guard and the 32-byte read cap are security requirements (a FIFO would block, `/dev/zero` is unbounded). Keep them through any refactor.
- #333 will later extend the sentinel token (e.g. `<digits>:<suffix>`). Keep invalid content falling back to `filemtime()`, never to "undatable", so that format stays readable.
- No version bump in this PR. The release (minor bump from `1.3.0`) is cut after both #311 and #331 are merged.
