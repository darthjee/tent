# Plan: Release tent and tent-test as multi-arch images

Issue: [300-release-tent-and-tent-test-as-multi-arch-images.md](../../issues/300-release-tent-and-tent-test-as-multi-arch-images.md)

## Overview

Move `darthjee/tent` and `darthjee/tent-test` to the push-by-digest multi-arch release that #299 introduced for `dev_tent-base`. Generalise the CircleCI release/verify jobs over `[base, tent, tent-test]`, drop the per-arch (`-arm64`) code paths, and make `merge` repair a stale `latest`.

See [infra.md](infra.md) for the full plan.
