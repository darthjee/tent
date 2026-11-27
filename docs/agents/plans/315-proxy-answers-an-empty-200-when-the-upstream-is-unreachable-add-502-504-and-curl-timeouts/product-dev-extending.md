# Product-dev-extending Plan: Proxy answers an empty 200 when the upstream is unreachable; add 502/504 and curl timeouts

Main plan: [plan.md](plan.md)

## Shared contracts

- `connect_timeout` (int seconds, default `5`) and `timeout` (int seconds, default `30`) are accepted by both `default_proxy` and `proxy`. Non-negative integers only; `0` disables that timeout; anything else makes `build()` throw `\InvalidArgumentException`.
- Upstream transport failure → `502`; cURL timeout (connect or total) → `504`. Empty body, no upstream headers. The status goes through response middlewares (a `StatusCodeMatcher` with `5xx` matches) and is not cached under the default `cacheCodes`.
- A warn-level log line records method, upstream URL without query string, errno and cURL error.
- An injected `httpClient` is always wrapped; the timeout options only configure the default cURL client.

## Implementation Steps

### Step 1 — Document timeouts and upstream failure behaviour

In `docs/request-handlers.md`:

- Add `connect_timeout` and `timeout` rows to the `default_proxy` Options table and to the `proxy` Options table (type, default, validation, effect).
- Add a short "Upstream failures and timeouts" subsection (linked from the table of contents) describing the `502` / `504` mapping, the empty body, the middleware/cache interaction, and the warn log line, with a config example setting both timeouts.

Verify every statement against the merged `tent` code (`ProxyRequestHandler::build()`, `DefaultProxyRequestHandler::build()`, `UpstreamFailureNormalizer`). Commit this as a separate documentation commit.

## Files to Change

- `docs/request-handlers.md` — new options rows and an upstream-failure section.

## Notes

- Depends on the `tent` work being in place; do this last.
