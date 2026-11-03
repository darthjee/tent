# Documentation

## `DOCKERHUB_DESCRIPTION.md`

Add a short **Supported platforms** section, for example after "Exposed Port" or near the top, before "Quick Start". It should say:

- The images are published as one multi-arch tag for `linux/amd64` and `linux/arm64`. Docker picks the right variant automatically.
- Older releases were published as `<v>` (amd64) and `<v>-arm64` (arm64). Those tags remain on Docker Hub.
- No new `-arm64` / `latest-arm64` tags are published. Use `<v>` / `latest` instead (`latest-arm64` no longer moves).

Do not hard-code a version number. The `update-description` job publishes this with the first multi-arch release.

## `.claude/agents/infra.md`

Replace the "Images are built for both `amd64` and `arm64` via CI." convention with a short description of the release flow:

- `dev_tent-base`, `tent` and `tent-test` are released by CI as multi-arch manifests:
  1. per arch, `release-digest` builds, pushes untagged by digest, and runs the `check` contract against that digest;
  2. then `release-merge` publishes `<v>` and `latest` with `docker buildx imagetools create`.
- No `-arm64` tags are published.
- Never `docker push` a locally built image over a released tag: it would replace the multi-arch manifest with a single-arch image.
- Branch builds run `verify` (build with `--load` and check, no push) for all three images.
- `verify-tent-test` pulls `darthjee/dev_tent-base:<BASE_VERSION>`. On a PR that bumps `BASE_VERSION` and the tent-test `FROM` together, it fails until the tag release publishes the base. That's expected: the tag workflow orders base → tent-test, so the PR is safe to merge (or ship the base bump in its own release first).
- Re-running `merge` re-points a stale `latest`.

## Files to Change

- `DOCKERHUB_DESCRIPTION.md`: add the Supported platforms section.
- `.claude/agents/infra.md`: describe the multi-arch push-by-digest release, the branch-level verify, the base-bump caveat and the no-local-push rule.
