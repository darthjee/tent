# Infra Plan: Production image: disable display_errors so PHP warnings never leak into responses

Main plan: [plan.md](plan.md)

## Shared contracts

Produce exactly:

- `dockerfiles/tent/php/tent.ini` -> `/usr/local/etc/php/conf.d/tent.ini` (0644) with `display_errors = Off`, `log_errors = On`, `upload_max_filesize = 20M`, `post_max_size = 25M`. `error_log` is not set and no other directive is added.
- `dockerfiles/tent/apache/00-tent-0-php-log-level.conf` -> `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf` containing `LogLevel warn php:info`, sorting before `00-tent-access-log.conf` (which ends with `IncludeOptional /etc/apache2/tent.d/*.conf`).
- `dockerfiles/dev_tent-base/`, `dockerfiles/dev_tent/` and `dockerfiles/tent-test/` stay unchanged.

## Steps

- [01 — Production PHP ini](infra/01-production-php-ini.md)
- [02 — Apache log level for mod_php](infra/02-apache-php-log-level.md)
- [03 — Image checks in build_docker_image.sh](infra/03-image-checks.md)

## CI Checks

- `dockerfiles/tent`, `scripts/`: `make ci-verify-tent ARCH=amd64` (CI job: `verify-tent-linux` / `verify-tent-macos`; arm64 runs the same with `ARCH=arm64`)
- `dev_tent-base` guard: `make ci-verify-base ARCH=amd64` (CI job: `verify` with `target: base`)
- `tent-test` guard: `make ci-verify-tent-test ARCH=amd64` (CI jobs: `verify-tent-test-linux` / `verify-tent-test-macos`)
- The same `check_image()` runs again after release in `make ci-check-digest-<target>` (job `release-digest`), so the new assertions must pass against the pushed digests too.

## Notes

- Delete the `printf` block; do not leave it next to the `COPY` (two writers for the same path).
- Use the literal `/usr/local/etc/php/conf.d/` destination, not `$PHP_INI_DIR`.
- Both `COPY` lines come before the existing `apache2ctl -t` step, so a broken conf fails the build.
- `CHECK_PHP` is shared by every image: the existing upload-limit assertion must keep passing for `dev_tent-base` and `tent-test`, which still write their limits with their own `printf` (untouched).
- The behavioural end-to-end harness (proxying to `tent_httpbin`, warning-raising middleware, `dev_tent` negative control) is **not** part of this plan; it is #334. Step 03's runtime probe is a cheap self-contained check inside `verify`, not that harness.
- Do not bump `VERSION`/`BASE_VERSION`: the version bump is a separate PR.
