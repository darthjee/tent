# Parameterize release jobs and make filters tag-only
Add commands `docker_login` and `setup_qemu` (the `tonistiigi/binfmt` step). Replace the six arch-specific release jobs with two parameterized jobs on the `machine` executor:

- `build-and-release-image` (params `target`: `tent` | `tent-test`; `arch`: `amd64` | `arm64`): for `arm64`, run `setup_qemu` and `./scripts/prepare_scripts_image.sh arm64` (guarded with `when: equal [arm64, << parameters.arch >>]`); then `make ci-build-<< parameters.target >>`, `make ci-check-<< parameters.target >>`, `docker_login`, and `make ci-release-<< parameters.target >>`, all with `ARCH=<< parameters.arch >> VERSION=${CIRCLE_TAG:-latest}`.
- `build-and-release-base` (param `arch`): `setup_qemu` for `arm64` only (no scripts image), `make ci-ensure-base ARCH=…`, `docker_login`, `make ci-release-base ARCH=…`.

In the workflow, invoke them with `name:` set to the existing job names (`build-and-release-linux`, `build-and-release-macos`, `build-and-release-tent-test-linux`/`-macos`, `build-and-release-base-linux`/`-macos`) so that `requires` (tent-test → base, `update-description` → linux/macos) and the docs keep working. `build-and-release-package` and `update-description` stay as they are, apart from the executor and filter changes.

Filters: define anchors at the top of `workflows` or in a top-level anchor block. Use `all_tags` (`tags: only: /.*/`) for the test/check jobs and `coverage_finalize`, and `release_filters` (`tags: only: /.*/`, `branches: ignore: /.*/`) for every release job, replacing the nonexistent `master` branch filter. Also use an anchor for the repeated six-job `requires` list.

Finally, run `circleci config process` and compare the expanded output with the original config to confirm each release job has the same steps and arguments.

## Files to Change
- `.circleci/config.yml` — add `docker_login`/`setup_qemu` commands and the parameterized release jobs; rewrite workflow entries with `name:`, anchors, and tag-only filters
