# Plan: Release dev_tent-base as a multi-arch image

Issue: [299-release-dev-tent-base-as-a-multi-arch-image.md](../../issues/299-release-dev-tent-base-as-a-multi-arch-image.md)

## Overview

Move `darthjee/dev_tent-base` from per-arch tags (`<v>` amd64 + `<v>-arm64`) to one multi-arch manifest, using push-by-digest, check-by-digest and an `imagetools create` merge. Add generic commands to `scripts/build_docker_image.sh` that #300 will reuse for `tent`/`tent-test`. Remove the `BASE_SUFFIX` chain and the unsafe local push targets, bump `BASE_VERSION` to `0.1.0`, and rewire CircleCI.

## Context

- `scripts/build_docker_image.sh` builds with `docker build --platform`, tags `<v>${ARCH_SUFFIX}`, and passes `--build-arg BASE_SUFFIX=${ARCH_SUFFIX}` to `tent-test`/`dev_tent`. `check` runs `CHECK_PHP` with `--entrypoint php` against `${IMAGE_NAME}:${version}${ARCH_SUFFIX}`. `ensure` pulls the version and builds when it's missing.
- The `Makefile` has `BASE_VERSION?=0.0.3`, the `ci-ensure-base`/`ci-release-base` targets, and the local `build-base`/`push-base`/`build`/`push`/`ensure-image` targets.
- In `.circleci/config.yml`, `build-and-release-base` (per arch: `ci-ensure-base`, login, `ci-release-base`) runs on tags only, and the `tent-test` jobs require `build-and-release-base-<arch>`.
- `dockerfiles/dev_tent/Dockerfile` and `dockerfiles/tent-test/Dockerfile` use `ARG BASE_SUFFIX=""` + `FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}`.
- The CircleCI `machine` executor (`ubuntu-2204:current`) has Docker with buildx and `jq`. `setup_qemu` and `docker_login` commands already exist.

## Steps

- [01 — Multi-arch commands in the build script](infra/01-multi-arch-build-script.md)
- [02 — Dockerfiles, Makefile and BASE_VERSION](infra/02-dockerfiles-and-makefile.md)
- [03 — CircleCI base jobs](infra/03-circleci-base-jobs.md)

## CI Checks

- Release jobs only run on git tags. On branches, the new `verify-base-<arch>` jobs (step 03) build and check the base with no push.
- Local: `bash -n scripts/build_docker_image.sh`, then `./scripts/build_docker_image.sh verify dev_tent-base amd64 0.1.0` (and `arm64` with QEMU installed).
- Local: `make build` after `make build-base` (`.claude/scripts/check_infra.sh`).
- `tent`/`tent-test` jobs keep their current per-arch flow (#300 migrates them).

## Notes

- **Post-merge action (not code):** cut Tent patch release `1.0.4` right after merge to run the push-by-digest/merge flow end to end. #301's release version moves up accordingly.
- Before `0.1.0` is published, local `make build`/`docker compose build` needs `make build-base` first. Mention this in the PR description.
- `latest-arm64` stays frozen at `0.0.3`. Mention this in the release notes.
- `.circleci/config.yml` is a root-level file, but it's handled here by infra because it's purely build/release wiring.
