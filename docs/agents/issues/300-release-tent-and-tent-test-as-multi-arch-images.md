# Issue: Release tent and tent-test as multi-arch images

## Description

Part of Epic #298. Builds on #299 (merged in #302), which made `dev_tent-base` multi-arch and added the generic push-by-digest commands.

`darthjee/tent` and `darthjee/tent-test` are released per arch: `<v>` is amd64 and `<v>-arm64` is arm64. Each `build-and-release-image` job in `.circleci/config.yml` builds, checks and pushes a single arch through `scripts/build_docker_image.sh` / the `Makefile` `ci-*` targets. On arm64, `scripts/prepare_scripts_image.sh` pulls `darthjee/scripts:<ver>-arm64` and retags it as `darthjee/scripts:<ver>`.

## Problem

- Consumers (e.g. Ward) have to choose a suffixed tag depending on their arch.
- `prepare_scripts_image.sh` relies on `-arm64` tags that `darthjee/scripts` no longer publishes (0.10.0+ are multi-arch only), which blocks bumping `darthjee/scripts`.

## Expected Behavior

- `docker buildx imagetools inspect darthjee/tent:<v>` and `darthjee/tent-test:<v>` list both `linux/amd64` and `linux/arm64`.
- No `-arm64` tags are published for new releases.
- Each arch's image passes `check` before the final `<v>` / `latest` tags are published.
- `scripts/prepare_scripts_image.sh` is gone.
- On every branch push, `verify` builds and checks `tent` and `tent-test` for both archs (after `all_checks`) without pushing.
- Re-running a release whose `latest` failed to update re-points `latest` to `<v>`.
- `deploy_frontend.sh` in the image is byte-identical to the one in the current release (still from `darthjee/scripts:0.9.0`).

## Solution

Reuse the push-by-digest flow #299 shipped for `dev_tent-base`. `scripts/build_docker_image.sh` already has generic `push-digest`, `check-digest`, `merge` and `verify` commands that work for any image, so no new script logic is needed.

1. **Makefile**: add `ci-push-digest-<target>`, `ci-check-digest-<target>`, `ci-merge-<target>` and `ci-verify-<target>` for `tent` and `tent-test`, using `$(VERSION)`. They mirror the existing `*-base` targets, which keep using `$(BASE_VERSION)`.
2. **CircleCI: generalise #299's base jobs** into one parameterised set, with `target` in `[base, tent, tent-test]`:
   - `release-digest(target, arch)`: checkout, QEMU on arm64, `setup_buildx`, `docker_login`, then `make ci-push-digest-<target>` (build and push untagged by digest), then `make ci-check-digest-<target>` (run the `check` contract against the pushed digest), then `persist_to_workspace` of `digests/`.
   - `release-merge(target)`: attach the workspace, then `make ci-merge-<target>` (`imagetools create` publishes `<v>` and `latest` from both digests).
   - `verify(target, arch)`: branch-level build with `--load` and a check, with no push (the generalised `verify-base`).
   - Remove `release-base-digest`, `release-base-merge`, `verify-base` and `build-and-release-image`.
3. **Workflow** (tag-only, `VERSION=$CIRCLE_TAG`):
   - `release-digest-base-{linux,macos}` → `release-merge-base`
   - `release-digest-tent-{linux,macos}` → `release-merge-tent` → `update-description`
   - `release-merge-base` → `release-digest-tent-test-{linux,macos}` → `release-merge-tent-test`

   Digest files are named per image and arch (`digests/<image>-<arch>`), so the workspaces of the three images never collide.

4. **Branch-level verify** (no push, every branch, no tag filter):
   - `verify-base-{linux,macos}` stays ungated, as today.
   - `verify-tent-{linux,macos}` and `verify-tent-test-{linux,macos}` require `all_checks`. Both images bake `source/`, so verifying a red build is pointless and gating saves machine minutes.
   - Both archs run, because arm64 is where this change carries risk (the amd64-pinned scripts stage).

Also:

- Delete `scripts/prepare_scripts_image.sh` and its CircleCI step.
- `dockerfiles/tent/Dockerfile`: pin the scripts stage to amd64 with `FROM --platform=linux/amd64 darthjee/scripts:0.9.0 AS scripts`. `darthjee/scripts:0.9.0` is published for `linux/amd64` only, and only the plain shell script `deploy_frontend.sh` is copied out of it (nothing runs in that stage), so the same file goes into both arch variants. This also works for local builds on arm64 hosts. `deploy_frontend.sh` stays unchanged here. #301 replaces this line with the multi-arch `darthjee/scripts:0.12.0` and drops the pin.
- `scripts/build_docker_image.sh`:
  - Remove the `build`, `ensure` and `release` commands and all `ARCH_SUFFIX` / `-arm64` handling. Legacy `-arm64` tags are dropped with no alias, and old tags stay on Docker Hub.
  - Keep `check <image> <arch> <version>`: a plain version now means the multi-arch tag `<image>:<version>`, run with `--platform linux/<arch>`, for spot-checking a published release. Full refs containing `@` or `:` are used as is, as today.
  - Update the help text.
- `Makefile`: remove `ci-build-tent`, `ci-release-tent`, `ci-check-tent`, `ci-build-tent-test`, `ci-release-tent-test` and `ci-check-tent-test`, and update `.PHONY`.
- `.claude/agents/infra.md`: describe the push-by-digest release (per-arch digest, check, then merge) for all released images.

### Edge cases

Already handled by the #299 commands:

- **Re-running a fully successful tag pipeline**: `push-digest` finds `<v>` complete and writes `digests/<image>-<arch>.skip`, and `check-digest` and the merge build are skipped.
- **One arch fails**: `merge` refuses to run without both digest files, so no tag is published. "Rerun from failed" keeps the successful job's workspace.
- **The check fails after the digest push**: an untagged digest is left in the registry. It's harmless, because no tag points to it.
- **The version already exists as a single-arch tag**: `push-digest` / `merge` fail loudly with "bump the version".
- **`latest`** moves on every release, as today.
- **tent-test** digest jobs require `release-merge-base`. Those base jobs are no-ops when the base is already complete.

Changes in #300:

- **Stale `latest` after a partial merge**: if `imagetools create` publishes `<v>` but fails before `latest`, a re-run currently returns early ("nothing to merge"). `merge` must, when `<v>` is already complete, compare the `latest` digest with `<v>`'s and, if they differ, run `docker buildx imagetools create -t <image>:latest <image>:<v>`. This applies to every image, including `dev_tent-base`.
- **Base-bump PRs**: branch-level `verify` of `tent-test` pulls `darthjee/dev_tent-base:<BASE_VERSION>` from Docker Hub, so on a PR that bumps `BASE_VERSION` and the `FROM` together it fails until the tag release publishes the new base. This is accepted. Document it in `.claude/agents/infra.md`: the tag workflow orders base → tent-test correctly, so such a PR is safe to merge (or the base bump can ship in its own release first).

### Backward compatibility

Documentation only, with no tag aliases:

- Existing tags (`<v>`, `<v>-arm64`, `latest-arm64`) remain on Docker Hub.
- No new `-arm64` / `latest-arm64` tags are published. `latest-arm64` stays frozen on the last per-arch release, which is the one silent regression, so users should switch to `<v>` / `latest`.
- arm64 hosts pulling `<v>` / `latest` now get a native arm64 image instead of the emulated amd64 one, with the same contents.
- Nothing in the repo references `-arm64` tags.

Add a short **Supported platforms** section to `DOCKERHUB_DESCRIPTION.md` stating the above: one multi-arch tag for `linux/amd64` and `linux/arm64`; older releases used `<v>` / `<v>-arm64` and those tags remain; use `<v>` / `latest` from now on. Do not hard-code a version number. `update-description` publishes it with the first multi-arch release.

### Out of scope

- Bumping `darthjee/scripts` to 0.12.0, the `VERSION` bump and the next Tent release (#301).
- `dev_tent`, which has no CI release job; none is added.
- The CircleCI `scripts` executor (`darthjee/scripts:0.6.0`) and `circleci_tent-base`.
- The local `make build` / `make build-base` targets.

### Testing

- **On the PR**: the branch-level `verify` jobs build `tent` and `tent-test` for both archs with `--load` and run the `check` contract, with no push.
- **On release**: each arch's pushed digest passes `check-digest` before `merge` publishes `<v>` / `latest`.
- **After release**: `docker buildx imagetools inspect darthjee/tent:<v>` and `darthjee/tent-test:<v>` list both platforms, and `scripts/build_docker_image.sh check tent arm64 <v>` passes against the published tag.
- The arm64 builds under QEMU are slower. That is accepted, since they run in parallel with amd64.
