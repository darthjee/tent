# Generalised CircleCI jobs

Replace `build-and-release-image`, `release-base-digest`, `release-base-merge` and `verify-base` in `.circleci/config.yml` with three jobs parameterised by `target`, an enum of `[base, tent, tent-test]`.

The Makefile target names line up: `ci-<action>-<target>`, so `ci-push-digest-base`, `ci-push-digest-tent` and so on.

## Jobs

```yaml
  release-digest:
    executor: machine
    parameters:
      target:
        type: enum
        enum: [base, tent, tent-test]
      arch:
        type: enum
        enum: [amd64, arm64]
    steps:
      - checkout
      - when:
          condition:
            equal: [arm64, << parameters.arch >>]
          steps:
            - setup_qemu
      - setup_buildx
      # push-digest pushes as part of the build, so log in first.
      - docker_login
      - run:
          name: Docker build and push by digest
          command: make ci-push-digest-<< parameters.target >> ARCH=<< parameters.arch >> VERSION=${CIRCLE_TAG:-latest}
      - run:
          name: Docker check pushed digest
          command: make ci-check-digest-<< parameters.target >> ARCH=<< parameters.arch >> VERSION=${CIRCLE_TAG:-latest}
      - persist_to_workspace:
          root: .
          paths:
            - digests

  release-merge:
    executor: machine
    parameters:
      target:
        type: enum
        enum: [base, tent, tent-test]
    steps:
      - checkout
      - attach_workspace:
          at: .
      - setup_buildx
      - docker_login
      - run:
          name: Docker merge multi-arch manifest
          command: make ci-merge-<< parameters.target >> VERSION=${CIRCLE_TAG:-latest}

  verify:
    executor: machine
    parameters:
      target:
        type: enum
        enum: [base, tent, tent-test]
      arch:
        type: enum
        enum: [amd64, arm64]
    steps:
      - checkout
      - when:
          condition:
            equal: [arm64, << parameters.arch >>]
          steps:
            - setup_qemu
      - setup_buildx
      - run:
          name: Docker build (no push) and check
          command: make ci-verify-<< parameters.target >> ARCH=<< parameters.arch >>
```

Delete `build-and-release-image`, including its `prepare_scripts_image.sh` step, as well as `release-base-digest`, `release-base-merge` and `verify-base`.

## Workflow (`test-and-release`)

Keep the job names readable and consistent, using `-linux` for amd64 and `-macos` for arm64 as today:

| Job name | Job (params) | requires | filters |
|---|---|---|---|
| `verify-base-linux` / `-macos` | `verify` (base, amd64/arm64) | none | none (branches only) |
| `verify-tent-linux` / `-macos` | `verify` (tent, …) | `*all_checks` | none |
| `verify-tent-test-linux` / `-macos` | `verify` (tent-test, …) | `*all_checks` | none |
| `release-digest-base-linux` / `-macos` | `release-digest` (base, …) | `*all_checks` | `*release_filters` |
| `release-merge-base` | `release-merge` (base) | both base digests | `*release_filters` |
| `release-digest-tent-linux` / `-macos` | `release-digest` (tent, …) | `*all_checks` | `*release_filters` |
| `release-merge-tent` | `release-merge` (tent) | both tent digests | `*release_filters` |
| `release-digest-tent-test-linux` / `-macos` | `release-digest` (tent-test, …) | the six checks + `release-merge-base` | `*release_filters` |
| `release-merge-tent-test` | `release-merge` (tent-test) | both tent-test digests | `*release_filters` |
| `update-description` | unchanged | `release-merge-tent` | `*release_filters` |

- Remove the old `build-and-release-linux`, `build-and-release-macos`, `build-and-release-tent-test-linux` and `build-and-release-tent-test-macos` entries.
- Digest files are per image and arch (`digests/<image>-<arch>`), so the workspaces attached by `release-merge-tent-test`, which include the base digests upstream, don't collide.
- The `release_filters` anchor (tags only, branches ignored) is unchanged. Jobs with no filter run on branches only, which is the intended behavior for `verify`.
- `build-and-release-package`, the test jobs, the executors and the commands (`setup_qemu`, `setup_buildx`, `docker_login`) are unchanged.

## Files to Change

- `.circleci/config.yml`: replace the four image jobs with `release-digest`, `release-merge` and `verify`, and rewrite the image part of the `test-and-release` workflow per the table above.
