# Product-dev-extending Plan: Production image: disable display_errors so PHP warnings never leak into responses

Main plan: [plan.md](plan.md)

## Shared contracts

Rely on infra producing exactly what the main plan's [Shared contracts](plan.md#shared-contracts) table lists: file paths, directive values, the `[php:warn]`/`[php:notice]`/`[php:info]` prefixes, the `zz-*.ini` and `tent.d` override mechanisms, and the dev images unchanged. Document those values verbatim; do not introduce new knobs or env vars.

## Steps

- [01 — Agent architecture doc](product-dev-extending/01-architecture-doc.md)
- [02 — Consumer reference guide](product-dev-extending/02-reference-guide.md)
- [03 — Docker Hub description](product-dev-extending/03-dockerhub-description.md)

## Notes

- Documentation is a separate commit after the infra commit(s), as the repository requires.
- Confirm the documented overrides against the built image, not from memory. With `make ci-verify-tent` built locally, start the image with (a) a `zz-local.ini` setting `display_errors = On` mounted in `conf.d` and (b) a `tent.d/*.conf` with `LogLevel warn`, and check that each takes effect as the docs describe. Report any mismatch to infra rather than rewording the docs around it.
- Do not document the version bump or a release date; the upgrade note is written so it can be pasted into the GitHub release notes.
- Mention #335 only as "planned"; do not describe its behaviour as existing.
