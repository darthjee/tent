# Issue: Release dev_tent-base as a multi-arch image

## Description
Part of Epic #298. Tent images are built and released one arch at a time, each under its own tag: `darthjee/dev_tent-base:<v>` is amd64 and `darthjee/dev_tent-base:<v>-arm64` is arm64. `scripts/build_docker_image.sh` adds an `ARCH_SUFFIX` to every tag. `dockerfiles/tent-test/Dockerfile` and `dockerfiles/dev_tent/Dockerfile` pick the matching base through a `BASE_SUFFIX` build arg (`FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}`).

`darthjee/scripts` (from `darthjee/docker`) moved to single multi-arch manifests built with buildx + QEMU (see `darthjee/docker`'s `bin/image.sh` and `.circleci/config.yml`).

## Problem
The base image is the root of the per-arch suffix chain. `tent-test` (and the local `dev_tent`) can't move to plain multi-arch tags until `dev_tent-base` is published as one.

## Expected Behavior
- `docker buildx imagetools inspect darthjee/dev_tent-base:<new BASE_VERSION>` lists both `linux/amd64` and `linux/arm64`.
- No `-arm64` tag is published for the new base version.
- `docker compose build` works for `dev_tent` on amd64 and arm64 hosts.
- `tent-test` still builds on both archs from the new base.
- Re-releasing with an unchanged `BASE_VERSION` skips the base build and leaves `latest` untouched.
- An existing single-platform tag for `BASE_VERSION` makes the base job fail with a clear message.
- Both `tent-test` jobs start only after the base merge job.
- The base check (`CHECK_PHP` contract plus `pcov` and `composer`) passes against each pushed digest on both archs.
- On branches, a no-push job builds and checks the base on amd64 and arm64.
- The `push-base` and `push` Makefile targets are gone.
- A Tent patch release (`1.0.4`) is cut right after merge and publishes `darthjee/dev_tent-base:0.1.0` as a multi-arch manifest.

## Solution
Release `dev_tent-base` as a single multi-arch manifest, keeping the image check before the final tag is published:

1. Per-arch CircleCI job (amd64 and arm64, QEMU on arm64): build and push an **untagged** image by digest, check that exact digest, and hand the digest to the merge job.
2. A merge job runs `docker buildx imagetools create` to publish `<BASE_VERSION>` and `latest` from the two per-arch digests.

See [Release flow: push by digest](#release-flow-push-by-digest) below.

Also:

- Bump `BASE_VERSION` to `0.1.0` (see [Versioning](#versioning)).
- Keep the `ensure` behavior (skip rebuilding when the base version already exists), now checking the multi-arch tag (see [Edge cases](#edge-cases)).
- Remove the `BASE_SUFFIX` build arg from `dockerfiles/dev_tent/Dockerfile` and `dockerfiles/tent-test/Dockerfile`, and point both at the new `BASE_VERSION` directly.
- `dev_tent` is only built locally by docker-compose, so it just picks the native variant of the base.
- Do not publish `-arm64` tags for the new base version.

### Scope
#### In scope

- Generic commands in `scripts/build_docker_image.sh` that #300 can reuse: `push-digest`, `check` taking a ref, `merge`, and a multi-arch-aware `ensure`.
- CircleCI base jobs: per-arch push-by-digest and check, then the merge job. Rewire both `tent-test` jobs to require the base merge job.
- `Makefile`: replace `ci-ensure-base`/`ci-release-base` with targets for the new commands, and bump `BASE_VERSION`.
- Remove `BASE_SUFFIX` from `dockerfiles/dev_tent/Dockerfile`, `dockerfiles/tent-test/Dockerfile` and the build-arg handling in `build_image()`.
- Local Makefile targets:
  - **Remove `push-base` and `push`.** They push a native single-arch build as `:$(BASE_VERSION)`/`:latest`, which would overwrite the multi-arch manifest and break the other arch. Releases happen only through CI.
  - **Keep `build-base`** as a local native single-arch build for debugging, like `darthjee/docker`'s `bin/image.sh build`.
  - **Keep `make build`** unchanged. It's used by `README.md`, `AGENTS.md`, `.claude/agents/infra.md` and `.claude/scripts/check_infra.sh`.
- A check for the base image, run against each pushed digest (see [Testing](#testing)).
- A branch-level CircleCI job that builds and checks the base on both archs **without pushing** (see [Testing](#testing)).

#### Out of scope

- The `tent`/`tent-test` release flow, their `ARCH_SUFFIX`/`-arm64` tags, `scripts/prepare_scripts_image.sh` and `DOCKERHUB_DESCRIPTION.md` (all in #300).
- The `darthjee/scripts` bump (#301).
- The CircleCI executor image `darthjee/circleci_tent-base:0.0.1`.
- Deleting old `0.0.3`/`0.0.3-arm64` tags from Docker Hub.
- Native arm64 runners.

### Release flow: push by digest
Per-arch images are pushed **by digest**, with no temporary tags. The check runs against the exact pushed image, and the final tags are only created once both archs pass.

#### Per-arch job (amd64, arm64)

- Use a buildx `docker-container` builder (`docker buildx create --use`, as `darthjee/docker` does). The default `docker` driver can't push by digest. arm64 also needs QEMU (`setup_qemu`).
- Build and push untagged:

  ```bash
  docker buildx build --platform linux/<arch> -f <Dockerfile> <context> \
    --output type=image,name=darthjee/dev_tent-base,push-by-digest=true,name-canonical=true,push=true \
    --metadata-file meta.json
  ```

- Read `containerimage.digest` from `meta.json`.
- Run the check against `darthjee/dev_tent-base@sha256:<digest>` (with `--platform linux/<arch>`). That is the exact image that was pushed, not a rebuild.
- Write the digest to `digests/dev_tent-base-<arch>` and `persist_to_workspace` it.

#### Merge job

- Requires both per-arch jobs, so it never runs if either build or check fails, and no tag ever points at an unchecked image.
- `attach_workspace`, then:

  ```bash
  docker buildx imagetools create \
    -t darthjee/dev_tent-base:<BASE_VERSION> -t darthjee/dev_tent-base:latest \
    darthjee/dev_tent-base@sha256:<amd64 digest> darthjee/dev_tent-base@sha256:<arm64 digest>
  ```

#### Shared tooling (reused by #300)

`scripts/build_docker_image.sh` gets generic commands that work for any image. #300 then only wires `tent` and `tent-test` into them:

- `push-digest <image> <arch> <version>`: build, push by digest, and write `digests/<image>-<arch>`.
- `check <image> <arch> <ref>`: the existing check, taking a digest ref instead of an arch-suffixed tag.
- `merge <image> <version>`: read both digest files and run `imagetools create` for `<version>` and `latest`.

#### Trade-offs considered

- **Temporary per-arch tags** (e.g. `<v>-build-<arch>`) were rejected. They're simpler, but they leave extra tags on Docker Hub, which brings back the clutter that dropping `-arm64` removes, and cleaning them up needs Docker Hub API delete rights.
- **Build with `--load`, check, then rebuild and push** was rejected. It builds twice and doesn't guarantee that the released image is the checked one.
- The untagged per-arch manifests remain on Docker Hub, hidden from the tag list. They're referenced by the final manifest list.

### Edge cases
#### Version tag already exists (`ensure`)

The base jobs run on every Tent release tag, and `BASE_VERSION` usually hasn't changed. Before building, check the tag with `docker buildx imagetools inspect darthjee/dev_tent-base:<BASE_VERSION>` (public, no login needed):

- **Complete** (lists both `linux/amd64` and `linux/arm64`): skip. The per-arch jobs do nothing and the merge job is a no-op, so `latest` is left untouched.
- **Incomplete** (one platform only, e.g. a legacy per-arch tag): **fail loudly** with a message such as `tag <v> exists but is not multi-arch; bump BASE_VERSION`. A published version is never silently overwritten.
- **Missing**: run the normal build, check and merge.

#### Half-finished release

- If one arch fails, the merge job doesn't run and no tag is created. The other arch's digest stays untagged on Docker Hub.
- CircleCI "Rerun from failed" reruns only the failed arch and keeps the successful job's workspace digest, so the merge then works.
- A full rerun rebuilds both archs and produces new digests. The earlier untagged manifests are orphaned, which is harmless.

#### Job ordering with `tent-test`

The `0.1.0` tag only exists after the **merge** job. Both `tent-test` per-arch jobs must therefore require the base merge job instead of `build-and-release-base-<arch>`; otherwise they race it and fail with `manifest unknown`.

#### Docker Hub login order

With push-by-digest, the build step itself pushes, so `docker_login` must run **before** `push-digest` (today it runs after the build).

#### Digest handoff

- Parse `meta.json` with `jq`, which is available on CircleCI machine images. Fail the job if the digest is empty.
- The merge job fails if either `digests/dev_tent-base-<arch>` file is missing, and never creates a single-arch manifest.

#### Concurrent releases

Two release tags pushed close together with the same new `BASE_VERSION` both build and merge, and the last merge wins. The content is the same, so this is accepted and needs no lock.

#### arm64 build time

Under QEMU, `pecl install pcov` and `docker-php-ext-install` compile under emulation, so the arm64 job is noticeably slower. This is accepted because the base is rarely rebuilt (see the skip above). Native arm64 runners are out of scope.

### Testing

#### Base-image check

`check` runs against each pushed digest (`darthjee/dev_tent-base@sha256:<digest>`, `--platform linux/<arch>`, `--entrypoint php`). It reuses the existing `CHECK_PHP` contract:

- GD loaded, with JPEG and PNG support, plus the image functions already listed in `CHECK_PHP`;
- `exif` loaded;
- `upload_max_filesize=20M` and `post_max_size=25M`;
- `php_uname('m')` matches the target arch (`x86_64` / `aarch64`).

It adds two base-specific checks:

- the `pcov` extension is loaded;
- `composer --version` runs successfully.

#### Branch-level build (no push)

Release jobs only run on git tags, so add a CircleCI job that runs on **branches** (not tags), one per arch (QEMU on arm64):

- Build `dev_tent-base` with the same Dockerfile and buildx `docker-container` builder, using `--platform linux/<arch> --load` and **no push** and no Docker Hub login.
- Run the same base-image check against the loaded image.

This catches Dockerfile, QEMU and check problems before merge. It runs on every branch push; path-based filtering is out of scope. The arm64 job is slower under QEMU, which is accepted. #300 extends the same job to `tent` and `tent-test`.

The `1.0.4` patch release right after merge (see [Versioning](#versioning)) remains the end-to-end test of the push-by-digest/merge flow.

### Versioning
- **`BASE_VERSION` → `0.1.0`.** A bump is required anyway: reusing `0.0.3` would hit the fail-loudly rule for incomplete tags. The image content is unchanged, but the minor bump marks the change of distribution scheme: `0.0.x` = per-arch tags (`<v>` amd64 + `<v>-arm64`), `0.1.0`+ = single multi-arch tag. This mirrors how `darthjee/scripts` documents "0.10.0+ is multi-arch".
- Update all three hardcoded references together: `Makefile` (`BASE_VERSION?=`), `dockerfiles/dev_tent/Dockerfile` and `dockerfiles/tent-test/Dockerfile`. They stay hardcoded, because compose builds don't go through the Makefile, so a build-arg indirection wouldn't remove the duplication.
- **Exercise the pipeline right after merge.** Release jobs run only on git tags, so cut a Tent patch release (`1.0.4`) as soon as this issue is merged. That runs the new base flow on its own, instead of first running it during the Ward-critical release in #301. `tent`/`tent-test` content is unchanged in that release (still per-arch until #300). #301's release version moves up accordingly.

### Backward compatibility
No compatibility code is needed beyond documentation:

- **Existing tags:** `0.0.3` (amd64) and `0.0.3-arm64` stay on Docker Hub untouched. Nothing in the repo references them after this change.
- **`latest-arm64` freezes:** after the first merge, `latest` becomes multi-arch, while `latest-arm64` stays at `0.0.3` and goes stale. Nothing in the repo uses it. Mention it in the release notes.
- **Local `dev_tent` on Apple Silicon:** docker-compose never passed `BASE_SUFFIX`, so Mac devs got the amd64 base under emulation. They now get the native arm64 variant, which is faster. `docker_volumes/vendor` holds plain PHP, and compiled extensions live in the image, so nothing needs wiping. Note: "rebuild with `make build` after pulling".
- **New `BASE_VERSION` before its release:** until the next release tag runs, `darthjee/dev_tent-base:0.1.0` isn't on Docker Hub, so local `make build`/`docker compose build` fails. Run `make build-base` first (it tags the version locally), as with any base bump today. CI test jobs are unaffected because they use the `circleci_tent-base` executor. Document this in the PR and release notes.
- **CircleCI job names:** the `build-and-release-base-linux`/`-macos` jobs are replaced by the per-arch push-by-digest jobs and a merge job. These are tag-only release jobs, not PR checks, so branch protection is unaffected.
- **`tent-test` consumers:** unaffected. The base content is unchanged apart from the version, and `tent-test` keeps its per-arch tags until #300.
