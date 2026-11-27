# Plan: Apache access log: make the format configurable and stop logging query strings and Referer

Issue: [314-apache-access-log-make-the-format-configurable-and-stop-logging-query-strings-and-referer.md](../../issues/314-apache-access-log-make-the-format-configurable-and-stop-logging-query-strings-and-referer.md)

## Overview
Make a query-string-free, `Referer`-free access-log format the default of the published `darthjee/tent` image (release 1.4.0). A preset-only `TENT_ACCESS_LOG_FORMAT` (`safe` | `combined`) is validated in `/etc/apache2/envvars` and selected at Apache parse time via `Define` + `<IfDefine>` in a build-time conf file. Consumers get a Tent-owned `/etc/apache2/tent.d/` drop-in directory that always wins. Separately, the one PHP debug line that writes a query string to stderr (`ProxyRequestHandler`'s upstream line) stops doing so. An image-level behaviour check in `check_image`, run as `app` on both architectures, guards the result.

## Agents involved

- [infra](infra.md) — Apache conf + envvars snippet, Dockerfile step, build context wiring, `CHECK_ACCESS_LOG` image check.
- [tent](tent.md) — `ProxyRequestHandler` debug line without query string, PHPUnit test, `docs/agents/architecture.md` Logger section.
- [product-dev-extending](product-dev-extending.md) — consumer-facing docs: `docs/guides/tent/reference.md` and `README.md`.

## Shared contracts

- **Environment variable** `TENT_ACCESS_LOG_FORMAT`, presets only:
  - unset or empty → `safe` (default);
  - `safe` → safe format;
  - `combined` → stock Debian `combined` / `vhost_combined`, unchanged;
  - anything else → Apache does not start; stderr: `TENT_ACCESS_LOG_FORMAT: invalid preset '<value>' (accepted: safe, combined)`, exit 1.
- **Safe format** (redefines the `combined` nickname):
  `%h %l %u %t "%m %U %H" %>s %O %D "%{User-Agent}i"`
  and `vhost_combined` as `%v:%p ` + the same string.
- **Example lines** for `GET /probe?token=SMOKE` with `Referer: http://x/?ref=LEAK` (from the prototype on `php:8.4-apache`):
  - safe: `127.0.0.1 - - [<time>] "GET /probe HTTP/1.1" 404 472 1040 "curl/8.14.1"`
  - combined: `127.0.0.1 - - [<time>] "GET /probe?token=SMOKE HTTP/1.1" 404 472 "http://x/?ref=LEAK" "curl/8.14.1"`
- **Paths in the image**:
  - `/etc/apache2/conf-enabled/00-tent-access-log.conf` — Tent's presets; its last line is `IncludeOptional /etc/apache2/tent.d/*.conf`.
  - `/etc/apache2/tent.d/` — empty at build time; consumers mount a `*.conf` file or a whole directory here. **A drop-in always wins** over the variable.
- **PHP debug line** (`ProxyRequestHandler`), `LOG_LEVEL=debug`:
  `[<STATUS>] - upstream response — method: <METHOD>, uri: <PATH>, upstream: <BASE_URL>/<PATH>` — never a `?query`. The upstream request itself still forwards the query string.
- **Residual risks to document** (not fixed): tokens embedded in the path are still logged; `%h` is personal data and is the reverse proxy's IP when Tent sits behind one.
- **Release**: 1.4.0. GitHub release notes (no `CHANGELOG.md`) carry a before/after line and the `TENT_ACCESS_LOG_FORMAT=combined` escape hatch. The `Makefile` `VERSION?=1.3.0` → `1.4.0` bump belongs to the release PR, not this change.
