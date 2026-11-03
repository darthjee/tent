# Multi-arch commands in the build script

Add generic, image-agnostic multi-arch commands to `scripts/build_docker_image.sh`, and extend the image check for the base. Keep the existing `build`/`ensure`/`release` commands working for `tent`/`tent-test` (still per-arch until #300).

## New helpers

- `tag_status <image_name> <version>`: run `docker buildx imagetools inspect --raw "<image_name>:<version>"`.
  - If it fails with "not found", print `missing`. Any other failure (network, auth) exits 1.
  - Otherwise parse with `jq`: if there is a `.manifests` list containing both `linux/amd64` and `linux/arm64` (ignoring `unknown/unknown` attestation entries), print `complete`.
  - Anything else (single-platform manifest list, or a plain single image like the legacy `0.0.3`) prints `incomplete`.
- `ensure_builder`: create and select a buildx `docker-container` builder if the current builder isn't one (`docker buildx create --use --name tent-builder`, reused if it already exists). The default `docker` driver can't push by digest.
- `digest_file <image> <arch>`: return `digests/<image>-<arch>`.

## New commands

- `push-digest <image> <arch> <version>`:
  - Run `tag_status`. On `complete`, write the marker `digests/<image>-<arch>.skip`, print a skip notice and exit 0. On `incomplete`, exit 1 with `tag <image_name>:<version> exists but is not multi-arch; bump the version`.
  - On `missing`: `ensure_builder`, then build with `docker buildx build --platform "$PLATFORM" -f "$DOCKERFILE" [--build-context ...] "$CONTEXT" --output type=image,name=$IMAGE_NAME,push-by-digest=true,name-canonical=true,push=true --metadata-file <tmp>/meta.json`.
  - Read `containerimage.digest` with `jq -r`, exit 1 if it's empty, and write it to `digests/<image>-<arch>` (create `digests/` first).
- `check <image> <arch> <version-or-ref>`: if the third argument contains `@`, use it as the full image reference; otherwise keep today's `${IMAGE_NAME}:${version}${ARCH_SUFFIX}`. This keeps `tent`/`tent-test` unchanged.
  - CI calls it with `"$IMAGE_NAME@$(cat digests/<image>-<arch>)"`.
  - A `check-digest <image> <arch>` convenience command reads the digest file itself, and exits 0 with a notice when the `.skip` marker exists.
- `merge <image> <version>`: run `tag_status`.
  - `complete`: no-op, exit 0 (also covers the case where both `.skip` markers exist).
  - `incomplete`: exit 1, with the same message as above.
  - `missing`: require both `digests/<image>-amd64` and `digests/<image>-arm64` (exit 1 naming the missing file, and never create a single-arch manifest), then run `docker buildx imagetools create -t "$IMAGE_NAME:$version" -t "$IMAGE_NAME:latest" "$IMAGE_NAME@<amd64>" "$IMAGE_NAME@<arm64>"`.
- `verify <image> <arch> <version>` (branch job, no push): `ensure_builder`, then `docker buildx build --platform "$PLATFORM" ... --load -t "$IMAGE_NAME:$version-verify-$arch"`, then run the check against that tag.

## Base-image check extras

Extend `image_config` with per-image check extras, set only for `dev_tent-base`:

- `CHECK_EXTENSIONS="pcov"`, passed to the container as `-e EXPECTED_EXTENSIONS=...`. `CHECK_PHP` gets a loop over `explode(',', getenv('EXPECTED_EXTENSIONS') ?: '')` that records `extension not loaded: <ext>`, so the default empty value is a no-op for `tent`/`tent-test`.
- `CHECK_COMPOSER=true`: after the PHP check, run `docker run --rm --platform "$PLATFORM" --entrypoint composer <ref> --version`. A non-zero exit fails the check.

## Cleanup

- Remove `BASE_SUFFIX_ARG` and the `--build-arg BASE_SUFFIX=...` logic from `image_config`/`build_image()`. `tent-test`/`dev_tent` no longer take it. `ARCH_SUFFIX` tagging for `tent`/`tent-test` stays until #300.
- Update `show_help` with the new commands.

## Files to Change

- `scripts/build_docker_image.sh`: the helpers, the `push-digest`/`check-digest`/`merge`/`verify` commands, `check` accepting a digest ref, the base check extras (`EXPECTED_EXTENSIONS`, composer), and removal of `BASE_SUFFIX` handling.
