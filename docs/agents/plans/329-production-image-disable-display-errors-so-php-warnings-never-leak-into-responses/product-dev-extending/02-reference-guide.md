# Consumer reference guide

In `docs/guides/tent/reference.md`, add a `## PHP error output` section near `## Access log`, with these parts:

- **Where diagnostics go:** container stderr (`docker logs` / `docker compose logs`), never the response body. Include an example line for each prefix. Deprecations are included because of `LogLevel warn php:info`. Command-line PHP inside the image writes its diagnostics to stderr.
- **Overriding:**
  - PHP settings: mount `/usr/local/etc/php/conf.d/zz-<name>.ini`, which is read after `tent.ini`. Add an explicit warning **not** to bind-mount over `tent.ini`: doing so replaces the whole production config and silently re-enables `display_errors`.
  - Log level: a `*.conf` with `LogLevel ...` in `/etc/apache2/tent.d/`, the existing drop-in folder. Cross-link the drop-in subsection under `Access log`.
- **Logs contain sensitive data:** diagnostics may include server paths and request data, so restrict access to the logs. Configure log rotation, because Docker's default `json-file` driver does not rotate (for example `--log-opt max-size=10m --log-opt max-file=3`, or the `logging:` keys in Compose). A client that can trigger a warning on every request produces one log line per request.
- **Source-tarball deployments** (`tent-<tag>-source.tar.gz`, no Docker): they must set `display_errors = Off` and `log_errors = On` themselves, with an error log whose level keeps deprecations (under Apache + mod_php, `LogLevel warn php:info`).
- **Upgrade note**, written so it can be reused in the release notes:
  - a request that raised a warning now returns the real upstream status and headers (previously a 200 with headers dropped);
  - new `[php:*]` lines on stderr may trigger log alerts;
  - stop bind-mounting over `tent.ini` and use `zz-*.ini`;
  - command-line diagnostics have moved from stdout to stderr;
  - the dev and test images are unchanged.
- If `## Container paths` lists image paths, add `/usr/local/etc/php/conf.d/tent.ini` and `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf`.

## Files to Change
- `docs/guides/tent/reference.md` — new `PHP error output` section, container-path entries.
