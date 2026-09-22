# Bump dev_tent-base and fix the arm64 base tag

The base image changed, so bump its version and point its consumers at the new tag. At the same time, fix an existing bug: the arm64 `tent-test` build uses the **amd64** base. arm64 images are tagged `<v>-arm64`, but `FROM darthjee/dev_tent-base:<v>` always resolves to the amd64 tag. Confirmed on Docker Hub: `tent-test:0.10.4-arm64` has the same layers as `dev_tent-base:0.0.2`, not `dev_tent-base:0.0.2-arm64`.

- `Makefile`: `BASE_VERSION?=0.0.2` → `BASE_VERSION?=0.0.3`.
- `dockerfiles/tent-test/Dockerfile` and `dockerfiles/dev_tent/Dockerfile`: replace the `FROM` line with
  ```dockerfile
  ARG BASE_SUFFIX=""
  FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}
  ```
- `scripts/build_docker_image.sh`: in `image_config`, set a new variable (e.g. `BASE_SUFFIX_ARG=true`) for `tent-test` and `dev_tent` (reset to empty at the top, like `EXTRA_BUILD_CONTEXT`). In `build_image`, when it's set, append `--build-arg "BASE_SUFFIX=${ARCH_SUFFIX}"` to `cmd` (so `""` for amd64 and `-arm64` for arm64). `image_config` runs before `arch_config`, so read `ARCH_SUFFIX` in `build_image`, after both are called.

## Files to Change
- `Makefile` — `BASE_VERSION` to `0.0.3`.
- `dockerfiles/tent-test/Dockerfile` — `ARG BASE_SUFFIX` + `FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}`.
- `dockerfiles/dev_tent/Dockerfile` — same.
- `scripts/build_docker_image.sh` — pass `--build-arg BASE_SUFFIX=$ARCH_SUFFIX` for `tent-test` and `dev_tent`.
