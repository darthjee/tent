# Image checks in build_docker_image.sh

Extend the existing per-image check framework rather than adding a CI job. These checks run in `verify` (amd64 and arm64) and again in `check-digest` on release.

## a) PHP ini assertions (all images, via `CHECK_PHP`)

- In `image_config()`, add a per-image variable, e.g. `CHECK_DISPLAY_ERRORS`, reset to `""` with the other `CHECK_*` switches: `off` for `tent`, `on` for `dev_tent-base` and `tent-test` (both built on the dev base). `dev_tent` is not verified in CI; set `on` for consistency.
- Pass it to the `php -r "$CHECK_PHP"` run in `check_image()` as `-e EXPECTED_DISPLAY_ERRORS=...`.
- In `CHECK_PHP`, when `EXPECTED_DISPLAY_ERRORS` is set:
  - `off`: fail unless `ini_get('display_errors')` is falsy (`''`, `'0'`, `'Off'`, case-insensitive) **and** `ini_get('log_errors')` is truthy (`'1'`/`'On'`).
  - `on`: fail unless `ini_get('display_errors')` is truthy. This is the dev regression guard: making the images behave the same later has to be a deliberate change to this check.
  - The existing `upload_max_filesize`/`post_max_size` assertion stays as it is, for every image.
- The CLI program reads the same `conf.d`, so this checks the file that mod_php loads.

## b) Apache ordering and level (tent only)

Add a `CHECK_PHP_LOG_LEVEL=true` switch for `tent` and a small check, alongside `CHECK_DEPLOY_SH`/`CHECK_ACCESS_LOG_SH`, run with `--entrypoint bash`. It asserts:
- `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf` exists and contains `LogLevel warn php:info`;
- in `LC_ALL=C ls /etc/apache2/conf-enabled/` order it comes before `00-tent-access-log.conf`;
- `/usr/local/etc/php/conf.d/tent.ini` is mode `644`.

## c) Runtime probe (tent only, cheap, self-contained)

`index.php` requires `configuration/configure.php` when it exists, and `.htaccess` sends every request to `index.php`. So a probe needs no upstream:

- Write a temporary `configure.php` (same temp-dir/`chmod` pattern as `check_access_log()`'s drop-in dir, readable by uid 1000) that calls `trigger_error('tent smoke warning', E_USER_WARNING)` and `trigger_error('tent smoke deprecated', E_USER_DEPRECATED)` and defines no rules.
- Using the same start, wait and poll pattern as `CHECK_ACCESS_LOG_SH` (run `apache2-foreground` in the background, poll with `curl`, `trap ':' TERM`), first request `/probe` **without** the mount to record the baseline status and `Content-Type`. Then request it with the file mounted read-only at `/var/www/html/configuration/configure.php`.
- Assert in the mounted run:
  - the status and `Content-Type` match the baseline;
  - the body contains neither `tent smoke` nor `Warning:` nor `Deprecated:`;
  - the Apache output contains a `[php:warn]` line with `tent smoke warning` and a `[php:info]` line with `tent smoke deprecated`. The second assertion proves `LogLevel warn php:info` is in effect.
- Collect all failures and print the Apache output on failure, as the access-log check does.
- If the no-rule baseline turns out to be unsuitable (for example the status depends on something else), keep only the body and stderr assertions and record that in the PR description. The full upstream-proxy check belongs to #334.

## Files to Change
- `scripts/build_docker_image.sh` — `image_config()` switches; `EXPECTED_DISPLAY_ERRORS` in `CHECK_PHP` and `check_image()`; new log-level and runtime-probe checks wired into `check_image()` for `tent`; usage/help comments updated if they list the checks.
