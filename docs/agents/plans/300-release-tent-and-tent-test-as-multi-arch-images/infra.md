# Plan: Release tent and tent-test as multi-arch images

Issue: [300-release-tent-and-tent-test-as-multi-arch-images.md](../../issues/300-release-tent-and-tent-test-as-multi-arch-images.md)

## Overview

Reuse the generic `push-digest` / `check-digest` / `merge` / `verify` commands in `scripts/build_docker_image.sh` (added in #299) for `tent` and `tent-test`:

- Remove the legacy per-arch commands and `ARCH_SUFFIX`.
- Make `merge` re-point a stale `latest`.
- Pin the `darthjee/scripts:0.9.0` stage to amd64.
- Add Makefile targets.
- Replace the CircleCI base-only jobs and `build-and-release-image` with one parameterised set of jobs.

## Context

- Today, `tent` and `tent-test` are released per arch by `build-and-release-image`, which runs `ci-build-*` / `ci-check-*` / `ci-release-*` and publishes `<v>` (amd64) and `<v>-arm64`.
- On arm64, `scripts/prepare_scripts_image.sh` retags `darthjee/scripts:0.9.0-arm64`.
- `dev_tent-base` already uses `release-base-digest` → `release-base-merge` plus a branch-level `verify-base`.
- `darthjee/scripts:0.9.0` is published for `linux/amd64` only, and the tent image copies only `deploy_frontend.sh` (a shell script) from it.

## Steps

- [01 — Script cleanup and stale-latest repair](infra/01-script-cleanup-and-latest-repair.md)
- [02 — Dockerfile pin and Makefile targets](infra/02-dockerfile-and-makefile.md)
- [03 — Generalised CircleCI jobs](infra/03-circleci-generalised-jobs.md)
- [04 — Documentation](infra/04-documentation.md)

## CI Checks

- `scripts/`:
  - `bash -n scripts/build_docker_image.sh`
  - `shellcheck scripts/build_docker_image.sh` (if installed)
  - `scripts/build_docker_image.sh help`
- `Makefile`: `make -n ci-push-digest-tent ci-check-digest-tent ci-merge-tent ci-verify-tent ci-push-digest-tent-test ci-check-digest-tent-test ci-merge-tent-test ci-verify-tent-test ARCH=arm64 VERSION=1.0.4`
- `.circleci/config.yml`: `circleci config validate` (if the CLI is installed)
- Local smoke test (if Docker with buildx is available):
  - `make ci-verify-tent ARCH=amd64`
  - `make ci-verify-tent ARCH=arm64` (QEMU)
  - `make ci-verify-tent-test ARCH=amd64`
- CI jobs that exercise this on the PR: `verify-base-{linux,macos}`, `verify-tent-{linux,macos}`, `verify-tent-test-{linux,macos}`

## Notes

- **Docs ownership:** `DOCKERHUB_DESCRIPTION.md` and `.claude/agents/infra.md` are root and agent-config files, normally architect scope. They are kept in this plan as step 04, because they only document infra behavior, matching the precedent from #299.
- **Version numbering:** the release that follows #299 (1.0.4) has not been cut yet. #300 does not bump `VERSION`; that happens in #301, whose "release 1.0.4" wording may need updating depending on release order.
- **Base-bump PRs:** branch-level `verify-tent-test` is expected to fail on PRs that bump `BASE_VERSION` and the tent-test `FROM` together, until the new base tag is published. This is accepted and documented in step 04.
- **Re-runs:** `push-digest` skips (writing a `.skip` marker) when `<v>` is already multi-arch, so re-running a tag pipeline is safe.
