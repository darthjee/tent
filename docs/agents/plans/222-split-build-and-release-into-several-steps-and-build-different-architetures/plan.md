# Plan: Split build-and-release into Several Steps and Build Different Architectures

## Overview

Split the single `build-and-release` CircleCI job into three focused jobs and update the workflow dependency graph accordingly.

## Context

The current `build-and-release` job does three things in sequence:
1. Builds the source release package.
2. Builds a Docker image for `linux/amd64` and pushes it to Docker Hub.
3. Uploads the release package to GitHub.

This blocks parallelism and provides no ARM64 image. The issue requires separating these responsibilities and adding a dedicated ARM64 build job.

## Implementation Steps

### Step 1 — Create `build-and-release-package`

Extract the package-related steps from the current job into a new job:
- `./scripts/upload_release_package.sh build`
- `./scripts/upload_release_package.sh release`

This job keeps `machine: true` (needs Docker daemon for the build script) and the same `filters` and `requires` as the current `build-and-release`.

### Step 2 — Create `build-and-release-linux`

Extract the Docker steps targeting `linux/amd64` into a new job:
- Docker build with `--platform linux/amd64`
- Docker login
- Docker push (tagged and latest)

Same `machine: true`, same `filters` and `requires`.

### Step 3 — Create `build-and-release-macos`

Create a new job mirroring `build-and-release-linux` but targeting `linux/arm64/v8`:
- Docker build with `--platform linux/arm64/v8`
- Docker login
- Docker push (tagged and latest)

Same `machine: true`, same `filters` and `requires`.

### Step 4 — Update the workflow

- Remove the `build-and-release` job entry from the workflow.
- Add `build-and-release-package`, `build-and-release-linux`, and `build-and-release-macos` entries, each requiring the same test jobs as the original.
- Update `update-description` to require `[build-and-release-linux, build-and-release-macos]`.

### Step 5 — Remove the old `build-and-release` job definition

Delete the now-unused `build-and-release` job block from the `jobs` section.

## Files to Change

- `.circleci/config.yml` — split one job into three, update workflow dependencies.

## Notes

- **Image tag strategy for ARM64**: The current job pushes `darthjee/tent:$TAG` and `darthjee/tent:latest` for `linux/amd64`. The `build-and-release-macos` job needs a tag strategy that does not conflict. Options:
  - Use architecture-specific tags (e.g., `darthjee/tent:$TAG-arm64`) and combine via a Docker manifest job later.
  - Push directly to the same tags if Docker Hub is configured to handle multi-arch manifests automatically.
  - This is an open question — the current issue description does not specify the tagging approach.
- `update-description` will no longer depend on `build-and-release-package`; verify this is intentional.
