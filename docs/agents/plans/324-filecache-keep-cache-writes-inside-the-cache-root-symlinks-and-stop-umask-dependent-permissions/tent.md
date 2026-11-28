# Tent Plan: FileCache: keep cache writes inside the cache root (symlinks) and stop umask-dependent permissions

Main plan: [plan.md](plan.md)

## Shared contracts

You produce all the contracts in [plan.md](plan.md#shared-contracts): `FolderLocation::contains()`, `FileUtils::writeAtomically()`, the fixed `0755`/`0644` modes, and the `cache store skipped` `warn` line. `product-dev-extending` documents the resulting behavior, so keep the log text exactly as specified.

## Steps

- [01 — Extract the containment helper](tent/01-extract-containment-helper.md)
- [02 — Add the atomic writer](tent/02-add-atomic-writer.md)
- [03 — Harden FileCache::store()](tent/03-harden-filecache-store.md)
- [04 — Sentinel and trash folder](tent/04-sentinel-and-trash-folder.md)

## CI Checks

- `source`: `docker compose run --rm tent_tests composer tests` (CI job: tests for `source/` in `.circleci/config.yml`)
- `source`: `docker compose run --rm tent_tests composer lint` (CI job: "Run Lint") — PSR-12, PHPMD

## Notes

- Read path stays untouched: no `realpath()` / `stat` added to `exists()`, `content()`, `headers()`, `httpCode()`, `timestamp()`.
- TOCTOU between the containment check and `mkdir`/`rename` is accepted (same limitation as #320); the post-`mkdir` re-check only narrows the window. Say so in the `store()` docblock.
- Specs that plant symlinks or call `umask(0)` must restore the umask and clean up their temp trees in `tearDown()`.
- Mention in the PR: modes are now fixed `0755`/`0644` (group-shared caches need adjusting), and sub-directories symlinked to outside the cache root stop being cached there.
- `FileCache::remove()` is out of scope (`unlink` never follows a link).
