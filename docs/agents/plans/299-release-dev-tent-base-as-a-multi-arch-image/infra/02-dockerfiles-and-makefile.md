# Dockerfiles, Makefile and BASE_VERSION

Point the images built on the base at `0.1.0` with no suffix. Wire the new script commands into the Makefile and remove the targets that could overwrite the multi-arch manifest.

## Dockerfiles

- `dockerfiles/dev_tent/Dockerfile`: drop `ARG BASE_SUFFIX=""`, so the line becomes `FROM darthjee/dev_tent-base:0.1.0`.
- `dockerfiles/tent-test/Dockerfile`: same change.
- `dockerfiles/dev_tent-base/Dockerfile`: no change.

## Makefile

- `BASE_VERSION?=0.1.0`.
- Replace `ci-ensure-base`/`ci-release-base` with:
  - `ci-push-digest-base`: `./scripts/build_docker_image.sh push-digest dev_tent-base $(ARCH) $(BASE_VERSION)`
  - `ci-check-digest-base`: `./scripts/build_docker_image.sh check-digest dev_tent-base $(ARCH) $(BASE_VERSION)`
  - `ci-merge-base`: `./scripts/build_docker_image.sh merge dev_tent-base $(BASE_VERSION)`
  - `ci-verify-base`: `./scripts/build_docker_image.sh verify dev_tent-base $(ARCH) $(BASE_VERSION)`
- **Remove `push-base`, `push` and `ensure-image`.** They push a native single-arch build as `:$(BASE_VERSION)`/`:latest`, which would overwrite the multi-arch manifest. `ensure-image` is only used by `push`.
- **Keep `build-base`** (local native single-arch build, tagged `:$(BASE_VERSION)` locally) and **keep `build`** unchanged. `build` is used by `README.md`, `AGENTS.md`, `.claude/agents/infra.md` and `.claude/scripts/check_infra.sh`.
- Update `.PHONY` accordingly.

## Files to Change

- `dockerfiles/dev_tent/Dockerfile`: remove `BASE_SUFFIX`, `FROM darthjee/dev_tent-base:0.1.0`.
- `dockerfiles/tent-test/Dockerfile`: remove `BASE_SUFFIX`, `FROM darthjee/dev_tent-base:0.1.0`.
- `Makefile`: `BASE_VERSION` bump, new `ci-*-base` targets, removal of `push-base`/`push`/`ensure-image`, `.PHONY`.
