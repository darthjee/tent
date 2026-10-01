# Plan: Update CircleCI config from version 2 to 2.1

Issue: [292-update-circleci-config-from-version-2-to-2-1.md](../../issues/292-update-circleci-config-from-version-2-to-2-1.md)

## Overview
Rewrite `.circleci/config.yml` as a `version: 2.1` config, using executors, reusable commands, and parameterized jobs to remove duplication. Pin the deprecated `machine: true` to an explicit image, and make the release jobs explicitly tag-only. Job names, dependencies, and runtime behavior stay exactly the same.

`.circleci/` is a root-level folder that no specialist agent covers, so the **architect** owns this work.

## Context
- The current config (389 lines) uses `version: 2` with `workflows: version: 2`.
- Test/check jobs: `unit_test` (tent-base + httpbin sidecar), `checks`, `dev_api_test` (tent-base with env + `cimg/mysql:8.0` sidecar), `dev_api_checks`, `dev_frontend_test`, `dev_frontend_checks` (node image), `coverage_finalize`.
- Release jobs (`machine: true`): `build-and-release-package`, `build-and-release-{linux,macos}` (`ci-*-tent`), `build-and-release-tent-test-{linux,macos}` (`ci-*-tent-test`), `build-and-release-base-{linux,macos}` (`ci-ensure-base` / `ci-release-base`, no check step), and `update-description` (docker `darthjee/scripts:0.6.0`).
- macOS (arm64) variants add a QEMU setup step (`tonistiigi/binfmt`); the tent and tent-test arm64 jobs also run `./scripts/prepare_scripts_image.sh arm64`, but the base arm64 job does not.
- Release jobs filter on branch `master`, which doesn't exist (the default branch is `main`), so today they run only on tags. That behavior must be kept, and the config should state it explicitly.
- Job names are referenced in `docs/agents/dev-app.md` and `docs/agents/contributing.md`; keeping the names unchanged avoids any doc churn.

## Steps

- [01 — Bump version and add executors](plan/01-bump-version-and-executors.md)
- [02 — Extract reusable commands for test/check jobs](plan/02-test-check-commands.md)
- [03 — Parameterize release jobs and make filters tag-only](plan/03-parameterized-release-jobs.md)

## CI Checks
- `.circleci/config.yml`: `circleci config validate` (if the CLI is available locally) and `circleci config process .circleci/config.yml` to inspect the expanded config; otherwise rely on the CircleCI pipeline of the PR itself (all test/check jobs must run and pass on the branch).
- Release jobs can't be exercised on a branch (tag-only). Check them by diffing the processed (expanded) config against the original: same steps, commands, `ARCH`/`VERSION` values, and `requires`.

## Notes
- Machine image: use `ubuntu-2204:current` (or the current recommended LTS image). The release steps need Docker with buildx/QEMU support, which the Ubuntu machine images provide.
- Executors can't hold job-specific sidecar services cleanly, so `unit_test` and `dev_api_test` may keep their own inline `docker:` blocks (or use a parameterized executor). Prefer clarity over forced reuse.
- Tag-only filter: `tags: { only: /.*/ }` plus `branches: { ignore: /.*/ }`. Remove the `master` filter.
- Use YAML anchors (`&tag_filters`, `&release_filters`, `&all_checks`) for the repeated `filters`/`requires` blocks. Anchors must be defined before they are used in the file.
