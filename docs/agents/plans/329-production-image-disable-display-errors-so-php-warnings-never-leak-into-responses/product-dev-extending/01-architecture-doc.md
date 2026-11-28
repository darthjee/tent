# Agent architecture doc

In `docs/agents/architecture.md`, add a `#### PHP error output` subsection next to the existing `#### Apache access log` subsection, in the same style. It should cover:

- **Production image:** `display_errors = Off` and `log_errors = On` from `/usr/local/etc/php/conf.d/tent.ini`, with `error_log` left unset. Engine diagnostics therefore go to the Apache error log, which is linked to stderr: the same stream as the PHP `Logger`'s `error_log()` output.
- **Why:** `ResponseSender` emits status and headers only after the whole pipeline runs, so any printed diagnostic would corrupt the response (body prefixed, headers dropped, status forced to 200).
- **`LogLevel warn php:info`** (`00-tent-0-php-log-level.conf`, loaded before the `tent.d` include): without it, deprecations at `[php:info]` would be dropped. Show the line prefixes per severity.
- **`LOG_LEVEL`** filters only Tent's own messages, never engine diagnostics.
- **Dev/production divergence:** `dev_tent-base`, `dev_tent` and `tent-test` keep `display_errors` on deliberately (local debugging, PHPUnit output), and CI checks both sides in `scripts/build_docker_image.sh`.
- **What is not covered:** stray non-diagnostic output (an `echo`, a BOM) is not prevented by this (#335), and source-tarball deployments use their own PHP configuration.
- Link to the reference guide section from step 02.

## Files to Change
- `docs/agents/architecture.md` — new `PHP error output` subsection beside `Apache access log`.
