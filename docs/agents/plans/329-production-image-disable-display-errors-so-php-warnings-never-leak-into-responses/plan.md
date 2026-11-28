# Plan: Production image: disable display_errors so PHP warnings never leak into responses

Issue: [329-production-image-disable-display-errors-so-php-warnings-never-leak-into-responses.md](../../issues/329-production-image-disable-display-errors-so-php-warnings-never-leak-into-responses.md)

## Overview

Ship a versioned production PHP ini (`display_errors = Off`, `log_errors = On`, upload limits) in the `darthjee/tent` image, replacing the inline `printf`. Add an Apache conf that raises only mod_php's log level to `info`, so deprecations still reach stderr. Extend the existing per-image checks in `scripts/build_docker_image.sh` so CI asserts both the production values and the unchanged dev defaults. Then document the PHP error stream, the dev/production divergence, override mechanisms, log handling and the upgrade note. `source/` is not touched.

Commit order (repository rule): infra changes plus their checks first, documentation afterwards.

## Agents involved

- [infra](infra.md): image files, Dockerfile, build/check script
- [product-dev-extending](product-dev-extending.md): consumer and agent documentation, and confirming the documented overrides actually work

## Shared contracts

These values are fixed. Infra produces them; the docs describe them verbatim.

| Item | Value |
| --- | --- |
| PHP ini source file | `dockerfiles/tent/php/tent.ini` (in the `tent-assets` build context) |
| PHP ini in the image | `/usr/local/etc/php/conf.d/tent.ini`, mode `0644` |
| PHP directives | `display_errors = Off`, `log_errors = On`, `upload_max_filesize = 20M`, `post_max_size = 25M`; `error_log` unset; nothing else |
| Apache conf source file | `dockerfiles/tent/apache/00-tent-0-php-log-level.conf` |
| Apache conf in the image | `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf`; sorts before `00-tent-access-log.conf` |
| Apache directive | `LogLevel warn php:info` |
| Where diagnostics go | Apache error log -> container stderr. Line prefixes: `[php:warn]` (warnings), `[php:notice]` (notices and Tent `Logger` lines), `[php:info]` (deprecations), plus fatal errors at `[php:error]`/higher |
| PHP override for consumers | Mount `/usr/local/etc/php/conf.d/zz-*.ini` (read after `tent.ini`). Do **not** bind-mount over `tent.ini`; that replaces the whole production config and silently re-enables `display_errors`. |
| Log-level override for consumers | A `*.conf` with `LogLevel ...` in `/etc/apache2/tent.d/` (read after the image conf) |
| Dev/test images | `dev_tent-base:0.1.0`, `dev_tent`, `tent-test` unchanged: `display_errors` stays on |
| CLI inside the production image | diagnostics on stderr, not stdout |
| Related issues | #334 (e2e harness, sub-issue), #335 (code-level output guard; protects source-tarball users), #336 (`expose_php`) |
