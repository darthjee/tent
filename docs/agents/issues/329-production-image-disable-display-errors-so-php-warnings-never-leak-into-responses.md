# Issue: Production image: disable display_errors so PHP warnings never leak into responses

## Description

The production `tent` image (`dockerfiles/tent/Dockerfile`) is built `FROM php:8.4-apache`. The base image ships no active `php.ini` - it only copies the examples `php.ini-development` and `php.ini-production` into `$PHP_INI_DIR` - so PHP runs on its compiled defaults, where `display_errors` is on. Nothing in the repository overrides it: the only `conf.d` file the image writes today is `tent.ini`, carrying just `upload_max_filesize` and `post_max_size`. The base image also links Apache's `error.log` to `/dev/stderr` and keeps Debian's `LogLevel warn` in `apache2.conf`.

`source/source/index.php` builds the response through `RequestProcessor::handleRequest()` and only then calls `send_response()` -> `ResponseSender::send()`, the single place where status and headers are emitted. Tent's own `Logger` already writes through `error_log()`, i.e. to the Apache error log and therefore to stderr.

The development images (`darthjee/dev_tent-base:0.1.0`, inherited by `dev_tent` and `tent-test`) share the same PHP defaults. There, showing warnings in the response is the desired behaviour.

| Location | Behaviour today |
| --- | --- |
| [dockerfiles/tent/Dockerfile](https://github.com/darthjee/tent/blob/main/dockerfiles/tent/Dockerfile) | `FROM php:8.4-apache`. Writes `/usr/local/etc/php/conf.d/tent.ini` with only `upload_max_filesize=20M` and `post_max_size=25M`. Copies Apache assets from the `tent-assets` build context, runs `apache2ctl -t`, switches to `USER app`, `CMD ["apache2-foreground"]`. No `php.ini`, no `display_errors`, no `log_errors`. |
| [dockerfiles/dev_tent-base/Dockerfile](https://github.com/darthjee/tent/blob/main/dockerfiles/dev_tent-base/Dockerfile) | `FROM php:8.4-apache`. Same upload-only `tent.ini`. Base of both dev and test images, so it decides their PHP defaults too. |
| [dockerfiles/dev_tent/Dockerfile](https://github.com/darthjee/tent/blob/main/dockerfiles/dev_tent/Dockerfile) | `FROM darthjee/dev_tent-base:0.1.0`, inherits the display defaults unchanged. |
| [dockerfiles/tent-test/Dockerfile](https://github.com/darthjee/tent/blob/main/dockerfiles/tent-test/Dockerfile) | `FROM darthjee/dev_tent-base:0.1.0`, inherits the display defaults unchanged. |
| [source/source/index.php](https://github.com/darthjee/tent/blob/main/source/source/index.php) | Requires the loader and `configuration/configure.php`, then `RequestProcessor::handleRequest($request)` and `send_response($response)` - the only place headers and status are emitted, after the whole pipeline has run. |
| [source/source/lib/log/LoggerInstance.php](https://github.com/darthjee/tent/blob/main/source/source/lib/log/LoggerInstance.php) | Writes every message through `error_log('[LEVEL] ' . $message)`, i.e. already to the SAPI error log, filtered by `LOG_LEVEL`. |
| [docs/agents/architecture.md](https://github.com/darthjee/tent/blob/main/docs/agents/architecture.md) | Documents the Apache access log as a stream separate from the PHP `Logger` (`TENT_ACCESS_LOG_FORMAT`, drop-ins in `/etc/apache2/tent.d/`). Says nothing about where PHP engine errors go. |
| Base image [php:8.4-apache](https://github.com/docker-library/php/blob/master/8.4/bookworm/apache/Dockerfile) | Configures PHP with `--with-config-file-scan-dir=$PHP_INI_DIR/conf.d`, copies `php.ini-*` examples only, and links `$APACHE_LOG_DIR/error.log` to `/dev/stderr` and `access.log` to `/dev/stdout`. PHP is built as `mod_php` (`--with-apxs2`). |

### Context

Reported as [issue #329](https://github.com/darthjee/tent/issues/329), labelled `bug` and `shipit`, spawned from #326. Reproduced on a stock `php:8.4-apache` container with a script that raises a warning before setting headers; the deprecation-logging gap was reproduced the same way during refinement.

Related issues: #334 (sub-issue: end-to-end harness for the production image), #335 (code-level guard against stray output in `index.php`), #336 (`expose_php` / `X-Powered-By`).

Affected package: the `darthjee/tent` Docker image only (`source/` is unchanged). Related documentation: [docs/agents/architecture.md](https://github.com/darthjee/tent/blob/main/docs/agents/architecture.md) for the Apache log streams and the `Logger` classes, and the base image Dockerfile [php:8.4/bookworm/apache/Dockerfile](https://github.com/docker-library/php/blob/master/8.4/bookworm/apache/Dockerfile) for the `conf.d` scan directory and the `error.log` -> `/dev/stderr` link.

## Problem

Everything the middlewares and handlers do happens before the first byte is emitted, so any warning, notice or deprecation printed during a request is written to the client first and poisons the rest of the exchange:

- the proxied body is prefixed with HTML warning markup;
- `header()` calls fail with "headers already sent", so every upstream header (Content-Type, Set-Cookie, cache headers) is dropped;
- `http_response_code()` also fails, so the status collapses to 200 and an upstream 404 or 500 is reported as success;
- absolute server paths appear in the body.

A cache proxy that silently rewrites upstream status codes and drops headers is worse than a proxy that fails: the caller cannot tell a corrupted response from a valid one, and downstream caches may store the corrupted 200.

Turning `display_errors` off alone is not enough. Verified on stock `php:8.4-apache`: with `display_errors=Off`/`log_errors=On`, deprecations are reported by mod_php at `[php:info]` and dropped by the default `LogLevel warn`, so they would move from the body to nowhere.

The dev/production divergence is also undocumented: it is the reason a warning is visible in local runs and only in the logs in production.

## Expected Behavior

- A request that triggers a PHP warning returns body, headers and status byte-identical to the upstream response (behavioural proof in #334).
- Deprecations raised during a request are present in the container stderr at `[php:info]`.
- The warning text is present in the container stderr (`docker logs` / `docker compose logs`), prefixed by the PHP engine's own formatting.
- `ini_get('display_errors')` returns `'0'` / `'Off'` and `ini_get('log_errors')` returns `'1'` inside the production image.
- `upload_max_filesize=20M` and `post_max_size=25M` are still in effect.
- `dev_tent-base:0.1.0`, `dev_tent` and `tent-test` still show PHP warnings in the response, and the divergence is documented.
- No new writable path and no permission change for `USER app`.
- The image still passes `apache2ctl -t` at build time and still starts with `apache2-foreground`.

## Solution

Ship the production PHP settings as a versioned ini file in the `tent-assets` build context, replacing the inline `printf`; raise Apache's log level for the php module only so no diagnostic is lost; keep the dev and test images unchanged and document the divergence.

### Scope

#### In scope

- `dockerfiles/tent/php/tent.ini` with `display_errors = Off`, `log_errors = On` and the upload limits, copied into `conf.d`; the inline `printf` is removed.
- A static CI assertion on the production image: run `php -i` in the existing `verify` job (`make ci-verify-tent` -> `scripts/build_docker_image.sh verify tent`) and assert `display_errors => Off`, `log_errors => On`, `upload_max_filesize => 20M`, `post_max_size => 25M`.
- A dev regression guard: assert `display_errors` is still truthy in `dev_tent-base`, so a later normalisation of the images has to be a deliberate decision.
- Consumer documentation for source-tarball deployments (no Docker): the PHP settings they must set themselves (`display_errors = Off`, `log_errors = On`, error log level that keeps deprecations).
- Documentation: `docs/agents/architecture.md` (PHP error stream, `display_errors`/`log_errors`, dev-versus-production divergence), `docs/guides/tent/reference.md` and `DOCKERHUB_DESCRIPTION.md` (consumer-facing: where warnings go, how to override them with a `conf.d` drop-in).

#### Out of scope

- End-to-end behavioural test of the production image (warning-raising middleware against `tent_httpbin`, plus the `dev_tent` negative control): needs a harness that does not exist yet. Tracked as sub-issue #334.
- A code-level guard in `source/source/index.php` (output buffering before `ResponseSender` emits), which would protect responses even when `display_errors` is re-enabled: tracked separately as #335. This issue leaves `source/` unchanged.
- `expose_php` / `X-Powered-By`: tracked separately as #336.
- Protecting source-tarball deployments in code: #329 only documents the required PHP settings for them; the code-level protection is #335.
- Any other ini directive (`error_reporting`, `display_startup_errors`, `html_errors`, `memory_limit`).

### Changes

Ship the production PHP settings as a versioned file next to the existing Apache assets and copy it into `conf.d`, replacing the inline `printf`.

New file `dockerfiles/tent/php/tent.ini`:

```ini
; Production PHP settings for the tent image.
;
; A warning or notice printed during a request reaches the client before
; ResponseSender emits the headers, which corrupts the proxied response:
; body prefixed, upstream headers dropped, status forced to 200.
; Diagnostics must go to the log stream, never to the body.
display_errors = Off
log_errors = On
; error_log is left unset on purpose. Under mod_php PHP then writes to the
; Apache error log, which this image links to stderr, so both engine errors
; and Tent's own error_log() output land in `docker logs`.
upload_max_filesize = 20M
post_max_size = 25M
```

Change in `dockerfiles/tent/Dockerfile`, replacing the current upload-limits block:

```dockerfile
# PHP runtime settings: production ini (display_errors off, log_errors on,
# upload limits), shipped from the tent-assets context.
COPY --from=tent-assets php/tent.ini /usr/local/etc/php/conf.d/tent.ini
```

New file `dockerfiles/tent/apache/00-tent-0-php-log-level.conf`, copied to `/etc/apache2/conf-enabled/` next to the existing access-log conf:

```apache
# mod_php reports E_DEPRECATED / E_USER_DEPRECATED at [php:info]. The base
# image's apache2.conf sets `LogLevel warn`, which drops them. With
# display_errors Off they would otherwise vanish entirely; raising only the
# php module keeps Apache's own info messages quiet.
LogLevel warn php:info
```

The file name must sort **before** `00-tent-access-log.conf`, because that file ends with `IncludeOptional /etc/apache2/tent.d/*.conf`: a consumer drop-in in `tent.d` has to be read last to be able to override this level.

### Semantics

1. `display_errors = Off` removes engine diagnostics from the response body for every SAPI of the image, so the bytes the client receives are the bytes `ResponseSender` intended to send.
2. `log_errors = On` keeps the diagnostics, and `LogLevel warn php:info` makes sure Apache does not drop the lowest-severity ones; nothing becomes unobservable, it only changes destination. Verified on stock `php:8.4-apache` with `display_errors=Off`/`log_errors=On`: `error_log()` (Tent `Logger`) and `E_USER_NOTICE` arrive as `[php:notice]`, `E_USER_WARNING` as `[php:warn]`, but `E_USER_DEPRECATED` is emitted as `[php:info]` and is silently dropped by the default `LogLevel warn` - it only appears once the php module's level is raised to `info`.
3. `error_log` stays unset. Under `mod_php` the SAPI error logger is the Apache error log, already linked to `/dev/stderr`, so PHP errors and `LoggerInstance` output share one stream and one collection path.
4. Only those two PHP directives are added (plus the Apache `LogLevel` for the php module, see 2). `error_reporting`, `display_startup_errors`, `html_errors`, `memory_limit` and `expose_php` keep the base image values, so the change is exactly the one the issue describes.
5. The upload limits are not dropped; they move into the same file, so the image has a single authoritative PHP configuration file.
6. `dev_tent-base`, `dev_tent` and `tent-test` are untouched: `display_errors` remains on there, deliberately.

### Design decisions and alternatives

| Decision | Choice | Rationale |
| --- | --- | --- |
| Where the production settings live | Versioned `dockerfiles/tent/php/tent.ini`, copied into `conf.d` from the `tent-assets` build context | Smallest reviewable diff, co-located with the existing `apache/` assets, reuses a build context the Dockerfile already needs, and keeps the `/usr/local/etc/php/conf.d/tent.ini` path that already exists instead of creating a second ini file. |
| Copy `php.ini-production` instead | Rejected | It would also flip `memory_limit` to 128M, change `error_reporting`, disable `expose_php` and more - a bundle of unrelated behaviour changes inside a bug fix about response integrity. |
| Second ini file (`zz-tent.ini`) kept separate from the upload limits | Rejected | Two files for one image means two places to look when a limit or a flag misbehaves; the existing `tent.ini` is the natural home. |
| Dev and test images | Keep `display_errors` on; document the divergence | Local debugging depends on seeing warnings immediately. Making dev behave like production would hide the very symptom the issue is about, and `tent-test` needs PHPUnit diagnostics on stdout. |
| Destination of the PHP errors | Leave `error_log` unset; use the Apache error log, already linked to stderr | One stream for engine errors, Apache errors and Tent's `LoggerInstance`, which already calls `error_log()`. Needs no writable file for `USER app` and needs no new volume. |
| Deprecations dropped by Apache `LogLevel warn` | Ship `LogLevel warn php:info` in a conf file read before the `tent.d` include | Without it, deprecations move from the body to nowhere, contradicting "only the destination changes". Scoped to `php:` so Apache's own info chatter stays out. Rejected: accept-and-document (silent loss by default); `error_log = /dev/stderr` (bypasses the Apache stream and moves Tent's `Logger` output too). |
| `error_reporting` | Untouched (base image default) | Narrowing it would silently drop deprecations from the log - a second behaviour change, and the opposite of the issue's intent. |
| `expose_php` / `X-Powered-By` | Out of scope, separate issue (#336) | Real but distinct leak: it is a header advertisement, not a body corruption, and mixing it would blur the verification. |
| Code-level `ini_set('display_errors', '0')` in `index.php`/`configure.php`, gated on `PHP_ENV` | Rejected for this issue | Would also protect source-tarball deployments, but puts runtime policy in code, overrides the operator's own ini, needs PHPUnit specs and overlaps with the output guard in #335, which is the code-level answer. |
| `php_admin_flag display_errors off` in an Apache conf | Rejected | Cannot be undone by `ini_set` from request code, but consumers could then only override through Apache `tent.d`, not the usual PHP `conf.d`; does not cover the CLI SAPI; splits PHP settings across two config systems. |
| `display_errors = ${TENT_DISPLAY_ERRORS}` (ini env-var expansion) | Rejected | Same as the runtime knob below: one wrong env value silently re-enables the leak. |
| Source tarball deployments (`tent-<tag>-source.tar.gz`, built by `scripts/upload_release_package.sh` from `source/source`) | Documentation only in this issue | The image fix cannot reach them; their PHP config is the operator's. The consumer docs state the required settings (`display_errors = Off`, `log_errors = On`, and an error log that does not drop deprecations). Code-level protection for them is the scope of #335. |
| Runtime override knob | None added; consumers use a mounted `conf.d` drop-in | A wrong `ENV` can silently re-enable `display_errors`; a drop-in is explicit and already the documented extension mechanism for the Apache side. |

### Edge cases

- A consumer mounting its own `/usr/local/etc/php/conf.d/zz-*.ini` wins: `conf.d` is scanned alphabetically and `tent.ini` sorts before `zz-`.
- A consumer bind-mounting over `/usr/local/etc/php/conf.d/tent.ini` replaces these settings entirely; that is their responsibility and stays possible.
- Errors raised before the request is dispatched are governed by `display_startup_errors`, which stays at the base default (off); `display_errors = Off` additionally covers the request path.
- Fatal errors: with `display_errors = Off` they no longer reach the body, and they are still reported through the error log.
- The CLI SAPI of the same image inherits `display_errors = Off`; that is accepted for consistency, since the image's purpose is serving HTTP.
- Extension authors running their own specs against `tent-test` are unaffected, because that image keeps the development defaults.
- Deprecations: logged at `[php:info]`; reach stderr only because of `LogLevel warn php:info`. A consumer can lower or raise it through a `tent.d` drop-in (read after the image's conf). Accepted cost: deprecations raised by vendor code on every request are logged on every request; reducing that noise (e.g. narrowing `error_reporting`) is a separate, explicit decision.
- Notices reach stderr at `[php:notice]` under the default `LogLevel warn` (verified), so no extra level is needed for them; Tent's own `Logger` lines share that level and remain filtered only by `LOG_LEVEL`.
- Log lines are plain text (`PHP Warning:  ... in <file> on line <n>`) regardless of `html_errors`, since `html_errors` only affects displayed output.
- Output that is not an engine diagnostic - a stray `echo`, a UTF-8 BOM or whitespace outside `<?php` in a mounted middleware or configuration file - still reaches the client before the headers and still corrupts the response. `display_errors` cannot prevent it; tracked in #335.
- Diagnostics raised while `ResponseSender::send()` is already writing the body no longer append markup to the body.
- Running the image with a non-default `APACHE_RUN_USER` does not affect anything here: no log file path and no directory permission depends on this change.

### Performance & security

#### Security - what the change closes

- Information disclosure: today a warning writes absolute server paths, file and line numbers and the message contents (which may include upstream URLs or configuration values) into the client response. After the change they only reach the log stream.
- Silent response corruption propagating downstream: an upstream 404/500 reported as 200 with `Cache-Control` and every other upstream header dropped can be cached heuristically by a CDN or browser, i.e. a cache-poisoning vector. Tent's own `FileCache` is expected to be unaffected, because it stores the `Response` object built before `ResponseSender` emits anything (to confirm during planning).

#### Security - what the change shifts

- The diagnostics that used to leak now live in the container logs, which makes log access the relevant boundary. This is an operator concern and is documented, not enforced.
- Log amplification: a client able to trigger a warning or deprecation on every request (malformed header, unusual path) now produces one stderr line per request. With Docker's default `json-file` driver and no rotation, a sustained flood can fill the host disk. `LogLevel warn php:info` widens this slightly by including deprecations. Mitigation in this issue is documentation only.

#### Performance

- `display_errors = Off` and `log_errors = On` have no measurable cost on the request path.
- Writing a diagnostic to stderr costs roughly what writing it into the body did before; no change while diagnostics are rare.
- The only meaningful cost is log volume under the amplification scenario above.

**Documentation required** (in `docs/guides/tent/reference.md` and `DOCKERHUB_DESCRIPTION.md`): PHP diagnostics go to the container stderr and may contain server paths and request data; restrict access to logs; configure log rotation (e.g. `json-file` `max-size`/`max-file`) in production.

### Backward compatibility

No documented behaviour breaks; this is a bug fix and warrants a **patch** release. Following the repository's convention, the version bump is done in its own PR, not here, and there is no CHANGELOG file: the upgrade note below lives in the consumer docs (`docs/guides/tent/reference.md`, `DOCKERHUB_DESCRIPTION.md`) and is reused in the GitHub release notes.

Who observes a difference after upgrading the production image:

| Who | Before | After | Action |
| --- | --- | --- | --- |
| Clients of the proxy, on a request that raised a warning | 200, upstream headers dropped, body prefixed with warning markup | Upstream status, headers and body unchanged | None - intended. Mentioned in the upgrade note, since a workaround for the corrupted 200 would now see real 404/500s. |
| Consumers bind-mounting their own `/usr/local/etc/php/conf.d/tent.ini` (e.g. to raise upload limits) | Their file replaces upload limits only | Their file replaces the whole production config: `display_errors` silently returns to on | Upgrade note: do not replace `tent.ini`; put overrides in a `zz-*.ini` drop-in, which is read after it. |
| Operators reading the container logs | PHP diagnostics absent from stderr (they were in the body) | New `[php:warn]`, `[php:notice]` and `[php:info]` (deprecation) lines on stderr | Upgrade note: alerting on stderr volume/patterns may fire; see the log-rotation advice in Performance & security. |
| CLI usage inside the image (`docker exec ... php ...`) | Diagnostics on stdout | Diagnostics on stderr (CLI with `log_errors = On` and no `error_log`) | One line in the upgrade note. |
| Consumers overriding Apache `LogLevel` through `tent.d` | Their level applies | Their level still applies (`tent.d` is read after the new conf) | None. |
| `dev_tent`, `tent-test`, extension authors' spec runs | Warnings in the response | Unchanged | None. |
| Source-tarball deployments | Their own PHP config | Unchanged | Docs only (see Scope); code-level protection in #335. |

### Testing

Hook point: `scripts/build_docker_image.sh` already has a per-image check framework. `image_config()` sets `CHECK_*` switches per image (`tent` sets `CHECK_DEPLOY` and `CHECK_ACCESS_LOG`), `check_image()` runs `php -r "$CHECK_PHP"` against the image (for `verify`, the `--load`ed `<version>-verify-<arch>` tag), and `check_access_log()` already boots Apache with `tent.d` drop-ins. The new assertions extend that framework (e.g. a `CHECK_PHP_INI` switch for `tent` and `dev_tent-base`) instead of adding a new CI job, so they run in `verify` on both amd64 and arm64 and again in `check-digest` on release.

- Build check (`apache2ctl -t` already runs at build time, after the new `LogLevel` conf is copied): the image must still build and start.
- Static check that the effective Apache config contains `LogLevel warn php:info` and that `00-tent-0-php-log-level.conf` sorts before `00-tent-access-log.conf` in `conf-enabled/`.
- New CI check for the production image, added to the existing `verify` job, since `tent-test` is based on the dev image and cannot assert production `ini` values: build `darthjee/tent` and run it as `php -i`, asserting `display_errors => Off`, `log_errors => On`, `upload_max_filesize => 20M` and `post_max_size => 25M`.
- End-to-end behavioural verification (warning-raising middleware, byte-identical response, stderr capture, `dev_tent` negative control) is deferred to sub-issue #334.
- No new PHPUnit unit spec is required: no production PHP class changes.
- Regression guard for the divergence: assert `ini_get('display_errors')` is truthy inside `dev_tent-base`, so a later "fix" that normalises the images is caught and has to be an explicit decision.

### Implementation notes

- The production build already uses a named build context: the `Makefile` passes `--build-context tent-assets=dockerfiles/tent`, and CI reaches the same path through `scripts/build_docker_image.sh`, because `dockerfiles/tent/Dockerfile` already does `COPY --from=tent-assets apache/...`. Adding `php/tent.ini` under the same context needs no pipeline change.
- Add `COPY --from=tent-assets apache/00-tent-0-php-log-level.conf /etc/apache2/conf-enabled/00-tent-0-php-log-level.conf` alongside the existing access-log `COPY`, before the `apache2ctl -t` step.
- Delete the `printf` block instead of leaving it next to the copy: two writers for the same path would make the effective values order-dependent.
- Keep the new file world-readable (default `0644`); `mod_php` reads it at Apache startup, where the master process runs as `app`.
- Prefer the literal `/usr/local/etc/php/conf.d/` in the destination over `$PHP_INI_DIR` - both work, the literal path is greppable and independent of build-time variable expansion.
- Documentation to update in the same PR family: `docs/agents/architecture.md` (a subsection next to the existing Apache access log one, covering the PHP error stream, `display_errors`/`log_errors` and the dev-versus-production divergence), `docs/guides/tent/reference.md` (consumer-facing: where warnings go, how to override with a drop-in, log access and rotation advice - see Performance & security) and `DOCKERHUB_DESCRIPTION.md` if it lists image-level behaviour.
- No change to `LoggerInterface`/`LoggerInstance` or to `LOG_LEVEL` semantics; `LOG_LEVEL` keeps filtering Tent's own messages and does not affect engine errors.
- No version bump in this issue; it follows in a separate PR as usual (patch).
- Commits stay split as the repository requires: image change plus tests first, documentation afterwards.

## Benefits

- Proxied responses are byte-identical to upstream even when a PHP diagnostic fires: real status codes, all upstream headers, clean body.
- No server paths or diagnostic contents leak to clients, and no corrupted 200 can be cached downstream.
- Every diagnostic, including deprecations, stays observable on the container stderr, in the same stream as Tent's own `Logger`.
- One versioned, reviewable PHP config file for the production image, with the dev/production divergence documented and guarded by CI.
