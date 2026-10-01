# Issue: Update CircleCI config from version 2 to 2.1

## Description
Migrate `.circleci/config.yml` from config `version: 2` to `version: 2.1`, and use 2.1's reusable configuration features (executors, commands, parameterized jobs) to remove the heavy duplication in the current pipeline. Pipeline behavior must stay the same.

## Problem
- The config uses `version: 2` and the legacy `workflows: version: 2` key, so 2.1 features aren't available.
- The same Docker images (`darthjee/circleci_tent-base:0.0.1`, `darthjee/circleci_node:0.2.1`) are declared again in each job.
- Repeated step sequences: "remove other folders / set folder / copy vendor / composer install" across the PHP jobs, "Upload coverage to Codacy" across the test jobs, and "Docker login" across all release jobs.
- Six nearly identical release jobs (`build-and-release-{linux,macos}`, `build-and-release-tent-test-{linux,macos}`, `build-and-release-base-{linux,macos}`) differ only by architecture (`amd64`/`arm64`, plus the QEMU/scripts-image setup for arm64) and make target.
- Every workflow entry repeats the same `filters: tags: only: /.*/` block, and the release jobs repeat the same long `requires` list.
- Release jobs use `machine: true`, which CircleCI has deprecated in favor of an explicit machine image.
- Release jobs filter on branch `master`, which doesn't exist (the default branch is `main`). In practice they run only on tags, but the config doesn't say so.

## Expected Behavior
- The config declares `version: 2.1` and has no `workflows.version` key.
- `circleci config validate` passes.
- The same jobs run under the same conditions as today:
  - test/check jobs and `coverage_finalize` run on every branch and on every tag;
  - release jobs (`build-and-release-*`, `update-description`) run **only on tags**, with the same dependencies (tent-test images still wait for the matching base image; `update-description` still waits for the linux/macos tent images).
- Every release job still produces the same images/tags (`VERSION=${CIRCLE_TAG:-latest}`, same `ARCH`, same make targets).

## Solution
- Bump to `version: 2.1` and remove `workflows.version`.
- **Executors**: define `tent-base` (`darthjee/circleci_tent-base:0.0.1`), `node` (`darthjee/circleci_node:0.2.1`), and a `machine` executor pinned to an explicit image (e.g. `ubuntu-2204:current`) replacing `machine: true`. Keep the jobs that need sidecar services (`unit_test` with httpbin, `dev_api_test` with MySQL) correct, either with their own `docker` blocks or with parameterized executors.
- **Commands**: extract reusable steps, e.g. `prepare_folder` (parameterized by the subfolder to keep), `copy_vendor_and_install`, `upload_codacy_coverage` (parameterized by report path), `docker_login`, `setup_qemu` (QEMU + scripts image for arm64).
- **Parameterized jobs**: replace the six release jobs with parameterized job(s) (e.g. `build-and-release` with `target`/`arch` params, and a base variant for `ci-ensure-base`/`ci-release-base`), invoked from the workflow with `name:` so job names and `requires` dependencies stay the same.
- **Filters**: make release jobs tag-only explicitly (`tags: only: /.*/`, `branches: ignore: /.*/`) instead of the nonexistent `master` branch; use YAML anchors to deduplicate the repeated `filters`/`requires` blocks.

## Benefits
- A much shorter config that is easier to maintain; changing an image or step happens in one place.
- Gets rid of the deprecated `machine: true`.
- Release triggers are stated explicitly (tags only) instead of depending on a branch that doesn't exist.
