# Extract reusable commands for test/check jobs
Add a `commands:` section and replace the duplicated steps in the test/check jobs:

- `prepare_folder` (param `folder`, e.g. `source`, `dev/api`, `dev/frontend`; param `remove`, the sibling folder to delete): runs `checkout`, the "Remove other folders" step, and the "Set folder" step. Keep each job's exact shell semantics: `source` → `rm -rf dev` then `mv source old; mv old/* ./; rm -rf old;`; dev folders → `rm -rf source` then `mv <folder>/* ./; rm -rf dev;`. A `steps`-level `when` or two separate commands are fine if one parameterized command gets awkward.
- `composer_setup`: "Copy dependencies" (`cp /home/circleci/vendor/ ./vendor/ -R`) plus `composer install`.
- `upload_codacy_coverage` (param `report`: `coverage/clover.xml` or `coverage/lcov.info`): the partial Codacy upload.

Keep the step `name:` values readable. The dev_api_test DB steps (wait, create, migrate) stay inline, and fixing the "EnsurMigrate" step-name typo to "Migrate database" is fine.

## Files to Change
- `.circleci/config.yml` — add `commands`; rewrite `unit_test`, `checks`, `dev_api_test`, `dev_api_checks`, `dev_frontend_test`, `dev_frontend_checks` to use them
