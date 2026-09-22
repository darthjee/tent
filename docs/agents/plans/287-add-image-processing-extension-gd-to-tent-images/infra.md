# Infra Plan: Add image processing extension (GD) to Tent images

Main plan: [plan.md](plan.md)

## Shared contracts

Produce the runtime from the main plan's table in `darthjee/tent` and `dev_tent-base` (so also `tent-test`/`dev_tent`): `gd` (JPEG + PNG) and `exif` loaded, `upload_max_filesize=20M`, `post_max_size=25M` in `/usr/local/etc/php/conf.d/tent.ini`, `memory_limit` untouched. Base version `0.0.3`, Tent version `1.0.0`.

## Steps

- [01 — Install GD, exif and upload limits](infra/01-install-gd-exif-and-upload-limits.md)
- [02 — Bump dev_tent-base and fix the arm64 base tag](infra/02-bump-base-and-fix-arm64-base.md)
- [03 — Add the image check command](infra/03-add-image-check-command.md)
- [04 — Wire CI ordering and checks](infra/04-wire-ci-ordering-and-checks.md)
- [05 — Bump version to 1.0.0](infra/05-bump-version-to-1-0-0.md)

## CI Checks

- Images: build and check locally, e.g. `./scripts/build_docker_image.sh build tent amd64 local && ./scripts/build_docker_image.sh check tent amd64 local`. Do the same for `dev_tent-base` (with `ensure`/`build` at `0.0.3`), then `tent-test`. For arm64: `docker run --privileged --rm tonistiigi/binfmt --install all`, then the same with `arm64`. (CI jobs: `build-and-release-linux`, `build-and-release-macos`, `build-and-release-tent-test-linux`, `build-and-release-tent-test-macos`, `build-and-release-base-linux`, `build-and-release-base-macos`.)
- `.circleci/config.yml`: validate with `circleci config validate` if the CLI is available.

## Notes

- `scripts/bump_version.sh` (step 05) edits files outside infra's usual scope (`README.md`, `docs/guides/how-to-use-tent.md`, `source/composer.json`). This is sanctioned for this issue: run the script as-is, don't hand-edit those files.
- `.circleci/config.yml` is a root-level file (normally architect's), but the change is only release-job wiring for infra's own images and scripts, so infra owns it here.
- `docker-compose.yml`'s `base_build` builds `dev_tent` without build args, so `BASE_SUFFIX` defaults to `""` (the amd64 base), which is the current behavior. Leave it unchanged.
- The Docker build contexts are `source` (tent, tent-test) and `dev/api` (dev_tent-base), so `tent.ini` can't be `COPY`'d from `dockerfiles/`. Write it with `RUN printf …` instead (see step 01).
- `dev_tent-base:0.0.3` doesn't exist on Docker Hub until the `1.0.0` tag's CI run publishes it. Locally, build it (`./scripts/build_docker_image.sh build dev_tent-base <arch> 0.0.3`) before building `tent-test`.
