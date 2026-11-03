# Plan: Release dev_tent-base as a multi-arch image

Issue: [299-release-dev-tent-base-as-a-multi-arch-image.md](../../issues/299-release-dev-tent-base-as-a-multi-arch-image.md)

## Overview

Release `darthjee/dev_tent-base` as a single multi-arch (`linux/amd64` + `linux/arm64`) manifest at `BASE_VERSION=0.1.0`. Each arch is built and pushed untagged by digest, checked against that exact digest, and merged into `0.1.0`/`latest` only after both checks pass. A no-push branch job builds and checks both archs before merge. The infra specialist owns all the work.

See [infra.md](infra.md) for the full plan.
