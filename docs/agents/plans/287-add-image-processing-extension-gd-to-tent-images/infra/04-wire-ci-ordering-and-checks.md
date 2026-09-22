# Wire CI ordering and checks

Two CircleCI changes:

1. **Ordering.** All `build-and-release-*` jobs run in parallel today. On the first release, `tent-test` would build `FROM dev_tent-base:0.0.3` before it's published. In the `test-and-release` workflow:
   - `build-and-release-tent-test-linux`: add `build-and-release-base-linux` to `requires`;
   - `build-and-release-tent-test-macos`: add `build-and-release-base-macos` to `requires`.
2. **Checks.** In the job definitions `build-and-release-linux`, `build-and-release-macos`, `build-and-release-tent-test-linux` and `build-and-release-tent-test-macos`, add a step between "Docker build" and "Docker login":
   ```yaml
   - run:
       name: Docker check
       command: make ci-check-tent ARCH=amd64 VERSION=${CIRCLE_TAG:-latest}
   ```
   Use `ci-check-tent-test` for the tent-test jobs and `ARCH=arm64` for the macos jobs. The macos jobs already run "Set up QEMU" first, so the arm64 container runs emulated. A failing check stops the job before the push.

## Files to Change
- `.circleci/config.yml` — `requires` on the two tent-test workflow entries, and a "Docker check" step in the four tent/tent-test build-and-release jobs.
