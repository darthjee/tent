# Product-dev-extending Plan: Apache access log: make the format configurable and stop logging query strings and Referer

Main plan: [plan.md](plan.md)

## Shared contracts

Document exactly what [plan.md](plan.md#shared-contracts) defines: the `TENT_ACCESS_LOG_FORMAT` presets and error, the safe format string, the example before/after lines, `/etc/apache2/tent.d/` with "drop-in always wins", and the residual risks.

## Implementation Steps

### Step 1 — `docs/guides/tent/reference.md`
- Add a row to the "Container paths" table: `/etc/apache2/tent.d/` — Apache access-log overrides (`*.conf`, a file or a directory mount); always wins over `TENT_ACCESS_LOG_FORMAT`.
- Add an "Access log" section:
  - The variable and its presets: `safe` (the default, used when unset or empty) and `combined` (the legacy Debian line). Any other value stops the container with the error text from the contract.
  - The safe format string and what it drops (query string, `Referer`) and adds (`%D`, microseconds).
  - Before/after lines for the same request.
  - The precedence rule: a drop-in in `tent.d` always wins.
  - A minimal drop-in example: a `*.conf` redefining `LogFormat "..." combined`, mounted with `-v ./my-log.conf:/etc/apache2/tent.d/my-log.conf:ro`.
  - Residual risks: tokens in the path are still logged; `%h` is personal data / the reverse proxy's IP behind a proxy.
  - One line noting that this is a separate stream from `LOG_LEVEL`, and that the PHP debug upstream line no longer includes query strings.

### Step 2 — `README.md`
Next to the `LOG_LEVEL` bullet, add a `TENT_ACCESS_LOG_FORMAT` bullet: presets `safe` (default) / `combined`, with a link to the reference section.

## Files to Change
- `docs/guides/tent/reference.md` — container-paths row; new "Access log" section.
- `README.md` — environment-variable bullet.

## Notes
- Verify the documented drop-in example against the built image (`make ci-verify-tent ARCH=<native>` or a local `docker run`) before finalising wording.
