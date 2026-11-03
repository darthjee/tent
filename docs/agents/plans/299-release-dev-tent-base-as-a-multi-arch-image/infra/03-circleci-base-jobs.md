# CircleCI base jobs

Replace the per-arch `build-and-release-base` job with push-by-digest per-arch jobs plus a merge job on tags, and add a no-push verify job on branches.

## Commands

- Add `setup_buildx`: `docker buildx create --use --name tent-builder`, used by every job that runs the new script commands. The script's `ensure_builder` is the fallback.

## Jobs

- `release-base-digest` (machine executor, `arch` param `amd64|arm64`):
  1. `checkout`;
  2. `setup_qemu` when `arm64`;
  3. `setup_buildx`;
  4. `docker_login` (**before** the build, which pushes);
  5. `make ci-push-digest-base ARCH=<< parameters.arch >>`;
  6. `make ci-check-digest-base ARCH=<< parameters.arch >>`;
  7. `persist_to_workspace` (root `.`, paths `digests`). `push-digest` always writes either the digest file or the `.skip` marker, so the path is never empty.
- `release-base-merge` (machine executor): `checkout`, `attach_workspace` (at `.`), `setup_buildx`, `docker_login`, `make ci-merge-base`.
- `verify-base` (machine executor, `arch` param): `checkout`, `setup_qemu` when `arm64`, `setup_buildx`, `make ci-verify-base ARCH=<< parameters.arch >>`. No login and no push.
- Remove the `build-and-release-base` job.

## Workflow

- `release-base-digest-linux` (`amd64`) and `release-base-digest-macos` (`arm64`): `requires: *all_checks`, `filters: *release_filters`.
- `release-base-merge`: requires both digest jobs, `filters: *release_filters`.
- Both `build-and-release-tent-test-*` jobs: replace `build-and-release-base-linux`/`-macos` in `requires` with `release-base-merge`. This prevents `manifest unknown` races.
- `verify-base-linux` / `verify-base-macos`: no tag filter, so they run on branches only. They require nothing, so they run in parallel with the tests.

## Files to Change

- `.circleci/config.yml`: the `setup_buildx` command, the `release-base-digest`/`release-base-merge`/`verify-base` jobs, removal of `build-and-release-base`, and workflow rewiring (including the `tent-test` `requires`).
