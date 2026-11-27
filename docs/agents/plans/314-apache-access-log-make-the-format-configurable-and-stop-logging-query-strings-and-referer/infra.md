# Infra Plan: Apache access log: make the format configurable and stop logging query strings and Referer

Main plan: [plan.md](plan.md)

## Shared contracts

- `TENT_ACCESS_LOG_FORMAT`: unset/empty → `safe`; `safe`; `combined`; anything else → stderr `TENT_ACCESS_LOG_FORMAT: invalid preset '<value>' (accepted: safe, combined)`, exit 1.
- Safe format: `%h %l %u %t "%m %U %H" %>s %O %D "%{User-Agent}i"` as `combined`, and `%v:%p ` + the same string as `vhost_combined`.
- `/etc/apache2/conf-enabled/00-tent-access-log.conf`, ending with `IncludeOptional /etc/apache2/tent.d/*.conf`; `/etc/apache2/tent.d/` created empty. A drop-in always wins.
- Only `dockerfiles/tent` changes. `dev_tent-base`, `dev_tent` and `tent-test` stay untouched.

## Steps

- [01 — Apache conf and envvars snippet](infra/01-apache-conf-and-envvars.md)
- [02 — Dockerfile step and build-context wiring](infra/02-dockerfile-and-build-context.md)
- [03 — CHECK_ACCESS_LOG image check](infra/03-check-access-log.md)

## CI Checks
- `dockerfiles/tent`, `scripts/`: `make ci-verify-tent ARCH=amd64` (and `ARCH=arm64` with QEMU) — CircleCI `verify-tent-linux` / `verify-tent-macos`; on tags, `check-digest` runs the same `check_image`.

## Notes
- Do **not** bump `Makefile` `VERSION` here; that is the 1.4.0 release PR.
- The prototype ran as root. The `USER app` run is exactly what step 03 proves; if Apache cannot start as `app` in the check environment for unrelated reasons (e.g. binding port 80), report it rather than switching the check to root.
