# Issue: Split build-and-release into Several Steps and Build Different Architectures

## Description

The current CircleCI `build-and-release` step bundles two distinct responsibilities: publishing the package to GitHub and building/releasing a Docker image for `linux/amd64`. These should be separated into dedicated jobs, and a new job for the `linux/arm64/v8` architecture should be added.

## Problem

- The `build-and-release` step mixes package publishing with Docker image building, making the pipeline hard to maintain and extend.
- There is no support for building a Docker image targeting `linux/arm64/v8` (Apple Silicon / ARM-based systems).

## Expected Behavior

- `build-and-release-linux`: builds and releases a Docker image for the `linux/amd64` architecture.
- `build-and-release-macos`: builds and releases a Docker image for the `linux/arm64/v8` architecture.
- `build-and-release-package`: releases the package archive to GitHub.
- The `update-description` step depends on both `build-and-release-linux` and `build-and-release-macos`.

## Solution

- Split the existing `build-and-release` CircleCI job into three separate jobs:
  1. `build-and-release-linux` — targets `linux/amd64`
  2. `build-and-release-macos` — targets `linux/arm64/v8`
  3. `build-and-release-package` — publishes the GitHub release package
- Update the CircleCI workflow so that `update-description` lists both architecture jobs as dependencies.

## Benefits

- Cleaner separation of concerns in the CI pipeline.
- Enables parallel architecture builds, reducing total pipeline time.
- Adds ARM64 Docker image support for users on Apple Silicon or ARM-based servers.

---
See issue for details: https://github.com/darthjee/tent/issues/222
