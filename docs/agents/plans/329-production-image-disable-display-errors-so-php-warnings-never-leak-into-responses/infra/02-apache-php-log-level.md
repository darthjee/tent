# Apache log level for mod_php

Why: tested on stock `php:8.4-apache` with `display_errors=Off`/`log_errors=On`. mod_php reports `E_DEPRECATED`/`E_USER_DEPRECATED` at `[php:info]`, and the base `apache2.conf` sets `LogLevel warn`, so deprecations are dropped and disappear completely once they are no longer displayed. Notices (`[php:notice]`) and warnings (`[php:warn]`) already get through.

Create `dockerfiles/tent/apache/00-tent-0-php-log-level.conf`:

```apache
# mod_php reports E_DEPRECATED / E_USER_DEPRECATED at [php:info]. The base
# image's apache2.conf sets `LogLevel warn`, which drops them. With
# display_errors Off they would otherwise vanish entirely; raising only the
# php module keeps Apache's own info messages quiet.
LogLevel warn php:info
```

Copy it in `dockerfiles/tent/Dockerfile` next to the existing access-log `COPY`, before the `RUN ... apache2ctl -t` step:

```dockerfile
COPY --from=tent-assets apache/00-tent-0-php-log-level.conf /etc/apache2/conf-enabled/00-tent-0-php-log-level.conf
```

Ordering requirement: `conf-enabled/` is included alphabetically, and `00-tent-access-log.conf` ends with `IncludeOptional /etc/apache2/tent.d/*.conf`. This file must load **before** it so a consumer's `tent.d` drop-in can override the level. `00-tent-0-…` sorts before `00-tent-a…` in the C locale Apache uses; step 03 checks this. Also update the comment above the existing access-log `COPY` block if it describes the `apache/` assets as access-log only.

## Files to Change
- `dockerfiles/tent/apache/00-tent-0-php-log-level.conf` — new: `LogLevel warn php:info`.
- `dockerfiles/tent/Dockerfile` — add the `COPY` into `conf-enabled/` before `apache2ctl -t`.
