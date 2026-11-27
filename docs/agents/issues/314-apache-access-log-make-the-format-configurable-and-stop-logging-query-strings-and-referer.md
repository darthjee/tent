# Issue: Apache access log: make the format configurable and stop logging query strings and Referer

## Background

Tent runs on Apache (`FROM php:8.4-apache` in `dockerfiles/tent/Dockerfile`) and every request passes through the Debian vhost configuration inherited from that base image. Tent's Dockerfile enables exactly one Apache module, `a2enmod rewrite`; it does not add, replace or override any log directive.

The active vhost therefore still logs with the Debian default:

```apache
LogFormat "%h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined
CustomLog ${APACHE_LOG_DIR}/access.log combined
```

`access.log` is a symlink to `/dev/stdout`, so every line ends up in `docker logs`, and from there in whatever aggregation or file collector a consumer runs.

`%r` is the full request line: method, path **and query string**. Query strings are where OAuth callback `code` values, `state` values and password-reset tokens travel. The logged `Referer` can repeat those same values.

Tent's existing logging is a different subsystem and does not help here. `source/source/lib/log/` (`Logger`, `LoggerInstance`, `NullLoggerInstance`) is an application logger driven by `LOG_LEVEL`; it was introduced in #201 and refined in #205, #208 and #211. It writes through PHP's error stream and never touches Apache's access log.

There is also no configuration surface that can reach Apache. `docs/guides/tent/reference.md` documents exactly three container paths (`/var/www/html/configuration/`, `/var/www/html/static/`, `./cache`), and `docs/guides/tent/extending-tent.md` documents the PHP extension hook at `/var/www/html/extension/loader.php`. None of them is reached by Apache when it writes an access line, because that line is written after PHP has already returned.

## Problem

Consumers of the image inherit a log format that records live credentials, and there is no supported way to change it. The only workaround today is to mount a file into an undocumented Debian path such as `/etc/apache2/conf-enabled/` and hope the nickname override wins, which is fragile and unverifiable from Tent's own documentation.

This was hit in practice: `darthjee/kerghan` writes query strings and `Referer` into the log files of every self-hosted installation, and its own fix cannot live in its Tent extension for exactly the reason above (darthjee/kerghan#606).

## Solution

Add two documented surfaces, make the safe format the default, and close the one PHP-side path that writes query strings to the container's logs.

1. **Environment variable** `TENT_ACCESS_LOG_FORMAT`, which selects a **named preset** and never takes a raw format string:
   - `safe` (default when unset or empty): the safe format below.
   - `combined`: the legacy Debian format, verbatim, for consumers that need the old field layout.
   - Any other value makes the container fail at startup with a clear error that lists the accepted presets.

   Arbitrary formats are deliberately not accepted through the variable. They go through the drop-in mount (item 2), which keeps the variable free of directive-injection concerns.
2. **Documented drop-in directory** `/etc/apache2/tent.d/`, owned by Tent and advertised in `docs/guides/tent/reference.md` next to the three existing container paths. It gives a full override without forking the image. Tent's own access-log conf ends with `IncludeOptional /etc/apache2/tent.d/*.conf`, so consumers can mount a single file or a whole directory there. A directory mount here cannot hide Debian's stock `conf-enabled/` files, such as `docker-php.conf`, which wires PHP into Apache. The path also stays stable if Tent changes its base image.

The default format:

```apache
LogFormat "%h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" combined
```

- `%m %U %H` (method, path, protocol) replaces `%r`, so the query string is dropped.
- `%{Referer}i` is removed.
- `%D` (microseconds) is added, so diagnosis keeps a timing signal after `%r` is gone.
- `Cookie` and `Authorization` were never logged and stay out.

3. **PHP debug log fix**: `ProxyRequestHandler::processsRequest()` (`source/source/lib/request_handlers/ProxyRequestHandler.php`) logs `upstream: $url` at `debug`, where `$url` comes from `Server::fullUrl($path, $query)` and so carries the query string. `LoggerInstance` writes through `error_log()`, which in `php:8.4-apache` goes to Apache's error log (`/dev/stderr`) and from there to `docker logs`. Log the upstream URL without its query string (base URL plus path). Every other `Logger` call site already logs `requestPath()` only and is unaffected.

The change ships in the published Docker image as **1.4.0**, with `safe` as the default from that release onward. The consumer side expects an image tag, not a PHP API change.

## Scope boundaries

In scope:

- The `LogFormat` used by the Apache vhost, and the directive that ends up in the image.
- The environment variable that selects it, limited to named presets (`safe`, `combined`).
- The `/etc/apache2/tent.d/` include directory and its documentation.
- Tests that prove the rendered configuration is the safe one.
- Only the published `dockerfiles/tent` image.
- The `ProxyRequestHandler` debug line that logs the upstream URL with its query string (item 3 of the Solution).

Out of scope:

- The PHP-side `Logger` / `LOG_LEVEL` subsystem itself (levels, output stream, format). Unchanged apart from the one call site above.
- The Apache error log. It is a separate stream and is not governed by `LogFormat`; see Edge cases.
- PHP warnings, or what a consumer's own application writes.
- `dev_tent-base` and `dev_tent`: they run Apache for local development only and keep the Debian `combined` format, where full query strings help debugging. Changing them would also mean republishing the separate `dev_tent-base` base image.
- `tent-test`: not applicable. Its CMD is `vendor/bin/phpunit`, so Apache never runs and there is no access log.
- Raw `LogFormat` strings in `TENT_ACCESS_LOG_FORMAT`: covered by the drop-in mount instead.

## Alternative solutions

| Option | Why not chosen |
|---|---|
| Redact a list of known sensitive keys (`code`, `state`, `token`, `access_token`, ...) | A deny-list always lags the next credential name. `%U` drops the whole query string, which cannot be bypassed by a new parameter name. |
| Fix it only in the consumer (Kerghan mounts a `conf-enabled` file) | Reaches into an undocumented Debian path, depends on the nickname override and on `conf-enabled` loading before `sites-enabled`, and silently breaks if Tent changes its base image or vhost. It also leaves every other consumer exposed. |
| Log nothing (`CustomLog /dev/null`) | Destroys diagnosis for everybody. The path is still needed for triage. |
| A PHP class or middleware that rewrites the log | Impossible. Apache writes the line after PHP returns. |
| A `configuration/` key | Same reason: `configure.php` runs inside PHP, after the request is routed, and the access line is written by Apache outside that process boundary. |
| Opt-in safe format (keep `combined` as the default, flip it in a later major release) | Every consumer keeps leaking tokens until it acts, which defeats the purpose. The escape hatch (`TENT_ACCESS_LOG_FORMAT=combined`) already covers anyone who needs the old shape. |
| Accept a raw `LogFormat` string in `TENT_ACCESS_LOG_FORMAT` | Opens a directive-injection surface (newlines, quotes) that needs its own validation. Named presets plus the `tent.d` drop-in cover the same needs with no injection risk. |
| A custom Tent entrypoint that renders the conf from the variable at startup | The image runs as `USER app`, so the entrypoint would need a writable `/etc/apache2/conf-enabled/` file, letting the app user rewrite Apache config. `envvars` + `Define` selects the preset at parse time with everything written at build time. |
| Document a single drop-in file under `/etc/apache2/conf-enabled/` | It must be a file mount: a directory mount there would hide Debian's stock files, including `docker-php.conf`. It also ties consumers to a Debian path. A Tent-owned `/etc/apache2/tent.d/`, included after the presets, accepts file or directory mounts and stays stable across base-image changes. |
| Statically grep the effective Apache config for `%r` / `Referer` in the image check | Brittle with `<IfDefine>` presets, and redundant: the behaviour test in `check_image` and the build-time `apache2ctl -t` cover it. |
| Bump to 2.0.0 for the log-format change | The access-log format was never a documented contract, no PHP or configuration API changes, and one variable restores the old line. 1.4.0 with explicit release notes is enough. |

## Implementation notes

- **Mechanism: `envvars` + `Define`, no entrypoint.** The image runs as `USER app`, so anything that renders Apache configuration at runtime would need a writable `/etc/apache2/conf-enabled/` for the app user. Instead, everything is written at build time (while still `root`), and the preset is selected when Apache parses its configuration:
  - The stock `apache2-foreground` (inherited `CMD`, run via `docker-php-entrypoint`) sources `/etc/apache2/envvars` before `exec apache2`. Append the preset validation there: unset or empty → export `safe`; `combined` → keep; anything else → print `TENT_ACCESS_LOG_FORMAT: invalid preset '<value>' (accepted: safe, combined)` to stderr and `exit 1`, which stops the container.
  - Ship a static `/etc/apache2/conf-enabled/00-tent-access-log.conf` holding both presets:

    ```apache
    Define tent_access_log_${TENT_ACCESS_LOG_FORMAT}
    <IfDefine !tent_access_log_combined>
    LogFormat "%h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" combined
    LogFormat "%v:%p %h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" vhost_combined
    </IfDefine>
    IncludeOptional /etc/apache2/tent.d/*.conf
    ```

  - The `IncludeOptional` line sits last, after both presets, so whatever a drop-in redefines wins. Create `/etc/apache2/tent.d/` empty at build time, so the image behaves the same with or without a mount.

  - Under `combined`, nothing is redefined, so the stock Debian `combined` and `vhost_combined` from `apache2.conf` apply unchanged.
  - `vhost_combined` is redefined too because the base image enables `conf-enabled/other-vhosts-access-log.conf`, whose `CustomLog` uses that nickname and would otherwise still carry `%r` and `Referer`.
  - The environment value is only ever used to build a `Define` name that is checked by `<IfDefine>`. It is never placed in a `LogFormat`, and `envvars` has already rejected anything that is not a preset by then.
- **Load order, verified against `php:8.4-apache`.** `apache2.conf` defines the stock `LogFormat`s (lines ~212–216), then runs `IncludeOptional conf-enabled/*.conf`, then `IncludeOptional sites-enabled/*.conf`. The `000-default` vhost uses `CustomLog ${APACHE_LOG_DIR}/access.log combined`, so the redefined nickname wins. Files in `conf-enabled` load alphabetically, and the `00-` prefix puts Tent's file first. Consumer overrides do not depend on that ordering, because they are included from inside Tent's file, after its presets.
- **Prototype result** on `php:8.4-apache` for `GET /probe?token=SMOKE` with a `Referer: http://x/?ref=LEAK` header:
  - unset / `safe`: `"GET /probe HTTP/1.1" 404 472 1040 "curl/8.14.1"`
  - `combined`: `"GET /probe?token=SMOKE HTTP/1.1" 404 472 "http://x/?ref=LEAK" "curl/8.14.1"`
  - `bogus`: container exits with the error above.

  The prototype ran as root; the implementation must confirm the same behaviour under the shipped `USER app` (no runtime writes are needed, so it is expected to hold).
- **Where the files live.** The `tent` image's build context is `source/` (`scripts/build_docker_image.sh`, `CONTEXT="source"`), so files under `dockerfiles/tent/` cannot be `COPY`'d directly. Keep them as real files, e.g. `dockerfiles/tent/apache/00-tent-access-log.conf` and `dockerfiles/tent/apache/envvars-tent.sh`. Wire them in through a named build context, following the `tent-test` precedent: add `EXTRA_BUILD_CONTEXT="tent-assets=dockerfiles/tent"` to the `tent` case in `image_config()`, then `COPY --from=tent-assets ...` in the Dockerfile. The `Makefile` `build` target runs a plain `docker build -f $(DOCKER_FILE) source` and must gain the matching `--build-context` when building `tent`.
- **Dockerfile step.** Add a dedicated `RUN` **before `USER app`**, since it writes under `/etc/apache2/` and must run as root. It runs with `set -eu`, or with `&&` chaining. It:
  1. appends the envvars snippet to `/etc/apache2/envvars`;
  2. installs `00-tent-access-log.conf` into `conf-enabled/`;
  3. creates the empty `/etc/apache2/tent.d/`;
  4. runs `apache2ctl -t`, so a broken config fails the build instead of shipping.

  The existing dependency `RUN` chain uses `;` (`...exif; a2enmod rewrite; rm -rf ...`), so its exit status is that of `rm -rf`. That is a pre-existing weakness and is deliberately left unchanged here; do not append the new steps to it.
- **Version.** `Makefile` already has `VERSION?=1.3.0`. Bump it to `1.4.0` in the release PR that carries this change.
- Images have been published multi-arch since 1.1.0 (#299, #300, #301): one manifest per tag for `linux/amd64` and `linux/arm64`. Nothing here is architecture-specific, so no extra release work is needed beyond the existing `release-digest` and `release-merge` jobs.

## Backward compatibility

This is a deliberate behaviour change for anyone parsing Tent's access log, and it is on by default from the first release:

- The quoted request field contains `GET /path HTTP/1.1` instead of `GET /path?query HTTP/1.1`.
- The `Referer` field disappears, and an extra numeric field (microseconds) appears before the user agent.

Escape hatches, both documented:

- `TENT_ACCESS_LOG_FORMAT=combined` restores the previous line exactly.
- A drop-in `*.conf` mounted under `/etc/apache2/tent.d/` can define any format.

No PHP API, no `configuration/` rule, no cache and no file-format change. Consumers that never read the access log are unaffected.

Versioning decision:

- **Safe by default now.** There is no opt-in period: an opt-in default would leave every consumer leaking tokens until it acted, which defeats the purpose.
- **1.4.0, not 2.0.0.** The access-log format was never a documented contract of the image; it was inherited from the Debian base. The PHP API and the `configuration/` format do not change, and a single variable restores the old line, so a minor bump is enough. From this release on, the format *is* documented in `docs/guides/tent/reference.md`, so later changes to it should be treated as contract changes.
- **Communication.** The repository has no `CHANGELOG.md` and none is introduced. The GitHub release notes for 1.4.0 must flag the behaviour change and include a before/after line for the same request, plus the `TENT_ACCESS_LOG_FORMAT=combined` escape hatch.

## Edge cases

- Request with no query string: the logged path is the same as before, apart from the removed `Referer` and the added duration.
- Request with a trailing `?` and an empty query string: `%U` logs the path without the `?`.
- Encoded query strings and repeated parameters: dropped by `%U`, so nothing needs decoding.
- `TENT_ACCESS_LOG_FORMAT` set to an empty string: treat it as unset and use the `safe` preset.
- `TENT_ACCESS_LOG_FORMAT` set to anything other than `safe` or `combined` (including a raw format string, a newline or a `"`): fail at startup with a clear error naming the accepted presets. Because the value is only ever compared against preset names and never inserted into the configuration, it cannot inject a directive.
- Drop-in mounted while the variable is also set: **the drop-in always wins**, whatever preset is selected, because `tent.d/*.conf` is included after both presets. Document this precedence explicitly in `docs/guides/tent/reference.md`.
- `/etc/apache2/tent.d/` empty or not mounted: `IncludeOptional` matches nothing and the selected preset applies.
- Apache error log: not covered by `LogFormat`. Characterised: Tent's own `Logger` output reaches it through `error_log()`. The only call site that included a query string is the `ProxyRequestHandler` upstream line, which this issue fixes. All other call sites log `requestPath()` only. PHP warnings or exceptions raised by a consumer's own extension code are outside Tent's control and stay out of scope.
- Tokens carried in the path itself (e.g. `/reset/<token>`) are still logged by `%U`. This is a documented residual risk; consumers that route secrets through paths should use the drop-in for a custom format.
- Very long paths: `%U` truncates nothing itself; the existing line-length behaviour is unchanged.

## Testing strategy

- **Unit (PHPUnit).** Under `source/tests/unit/lib/request_handlers/ProxyRequestHandler/`, assert that the `debug` upstream line contains the path but not the query string for a request with a query (e.g. `/probe?token=SMOKE`).
- **Build time.** The new Dockerfile `RUN` ends with `apache2ctl -t`, so a broken Apache config fails the image build.
- **Image behaviour (`check_image`).** Add a `CHECK_ACCESS_LOG=true` flag to the `tent` case of `image_config()` in `scripts/build_docker_image.sh`, with an in-container `CHECK_ACCESS_LOG_SH` script run through `docker run --entrypoint bash`, following the `CHECK_DEPLOY` pattern. It runs as the image's default user (`app`), so it also proves the mechanism works without root. For each case, the script starts `apache2-foreground` in the background, requests `/probe?token=SMOKE` with a `Referer` such as `http://x/?ref=LEAK` via `curl`, captures the access line and asserts:
  - `TENT_ACCESS_LOG_FORMAT` unset, and set to an empty string → safe format: the line contains `/probe` and the `%D` duration field, and contains neither `SMOKE` nor `LEAK`.
  - `TENT_ACCESS_LOG_FORMAT=combined` → legacy format: the line contains `?token=SMOKE` and the `Referer` value.
  - `tent.d` drop-in wins: with `TENT_ACCESS_LOG_FORMAT=combined` set, write a `/etc/apache2/tent.d/*.conf` that redefines `combined` with a distinctive format (via a bind mount or a writable test layer), and assert that format is the one used.
- **No static config grep.** Reading the effective config and grepping for `%r` / `Referer` is dropped; with the `<IfDefine>` presets it would be brittle, and the behaviour test plus `apache2ctl -t` cover it.
- **CI.** `check_image` already runs from `verify` (every push, `linux/amd64` and `linux/arm64`) and from `check-digest` (tags, after `release-digest`), so a regression fails before a tag is published.

## Documentation

- `docs/guides/tent/reference.md`: add `/etc/apache2/tent.d/` to the container-paths table, with the rule that a drop-in always wins over the variable, and document `TENT_ACCESS_LOG_FORMAT` with both values. Document the residual risks: path-embedded tokens, and `%h` being personal data / the proxy's IP behind a reverse proxy.
- `README.md`: list `TENT_ACCESS_LOG_FORMAT` alongside `LOG_LEVEL` and the other environment variables.
- `docs/agents/architecture.md`: near the Logger section, note that the Apache access log is a separate stream from the PHP `Logger` and is configured by the image, not by PHP.
- `docs/agents/issues/<id>_<name>.md` and `docs/agents/plans/<id>_<topic>/plan.md`: follow the existing convention.
- GitHub release notes for 1.4.0 (no `CHANGELOG.md`) must state the change with a before/after line and the `combined` escape hatch, because consumers will see it.

## Security and performance considerations

- Security: the leak is the reason for this issue. Dropping the query string removes OAuth `code`, `state` and password-reset tokens from the log stream, and dropping `Referer` removes a second copy of the same values.
- PHP-side leak: at `LOG_LEVEL=debug`, the `ProxyRequestHandler` upstream line currently writes the full upstream URL, query string included, to stderr. This issue strips the query from that line, so no Tent-emitted log line carries a query string at any log level.
- Residual risks, documented in `docs/guides/tent/reference.md` rather than fixed:
  - Path-embedded tokens are still logged; the drop-in covers consumers that need a stricter format.
  - `%h` (client IP) stays in the `safe` preset because it is needed for triage and abuse handling. It is personal data, and behind a reverse proxy it is the proxy's address rather than the end user's.
- Information loss: paths remain, so triage by route still works; correlating two requests that differed only by query string is no longer possible from the access log. The PHP `Logger` at `LOG_LEVEL=debug` still emits its `[status] - reason` lines, now path-only, when deeper diagnosis is needed.
- Performance: `%D` adds negligible cost. Dropping two fields shortens each line slightly, which marginally reduces log volume.
- The error log remains a possible side channel and is called out above so it is handled explicitly rather than overlooked.

## Labels and review

Labels: `Feature`. This ships through the image plus one PHP log-line change; no public PHP API changes.

Reviewers: `infra` for the Dockerfile and the release path, `tent` for the `ProxyRequestHandler` log-line change and its PHPUnit test, `dev-api` for the image check, and a `security` pass on the default format and on the error-log side channel.

Related: darthjee/kerghan#606, the consumer that needs this in order to drop query strings and `Referer` from its standalone log files.
