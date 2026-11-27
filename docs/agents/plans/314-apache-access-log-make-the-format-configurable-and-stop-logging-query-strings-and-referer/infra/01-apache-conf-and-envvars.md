# Apache conf and envvars snippet
Create the two build-time files that implement the presets, kept as real files under `dockerfiles/tent/apache/`.

`00-tent-access-log.conf`:

```apache
# Tent access-log presets. Selected by TENT_ACCESS_LOG_FORMAT (validated in envvars).
Define tent_access_log_${TENT_ACCESS_LOG_FORMAT}
<IfDefine !tent_access_log_combined>
LogFormat "%h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" combined
LogFormat "%v:%p %h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" vhost_combined
</IfDefine>
# Consumer overrides: always win, because they load after the presets.
IncludeOptional /etc/apache2/tent.d/*.conf
```

`envvars-tent.sh`, which gets appended to `/etc/apache2/envvars`. That file is sourced by the stock `apache2-foreground` (under `set -e`) and by `apache2ctl`:

```sh
# Tent: validate TENT_ACCESS_LOG_FORMAT (presets only).
case "${TENT_ACCESS_LOG_FORMAT:-}" in
  ""|safe) export TENT_ACCESS_LOG_FORMAT=safe ;;
  combined) export TENT_ACCESS_LOG_FORMAT ;;
  *) echo "TENT_ACCESS_LOG_FORMAT: invalid preset '${TENT_ACCESS_LOG_FORMAT}' (accepted: safe, combined)" >&2; exit 1 ;;
esac
```

The value is only used to build a `Define` name; it never reaches a `LogFormat`.

## Files to Change
- `dockerfiles/tent/apache/00-tent-access-log.conf` — new; presets and the `tent.d` include.
- `dockerfiles/tent/apache/envvars-tent.sh` — new; preset validation.
