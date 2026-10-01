# Bump version and add executors
Change the top-level `version: 2` to `version: 2.1` and delete `workflows.version: 2`. Add an `executors:` section:

- `tent-base`: docker `darthjee/circleci_tent-base:0.0.1`
- `node`: docker `darthjee/circleci_node:0.2.1`
- `scripts`: docker `darthjee/scripts:0.6.0`
- `machine`: `machine: { image: ubuntu-2204:current }`, replacing every `machine: true`

Switch the jobs without sidecars (`checks`, `dev_api_checks`, `dev_frontend_test`, `dev_frontend_checks`, `coverage_finalize`, `update-description`, all release jobs) to `executor: <name>`. `unit_test` and `dev_api_test` keep their inline multi-image `docker:` blocks, since they need the httpbin/MySQL services and the DB environment variables.

## Files to Change
- `.circleci/config.yml` — version bump, remove `workflows.version`, add `executors`, point jobs at them
