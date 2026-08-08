# product-dev-extending Plan: add a header filter configuration for FileCacheMiddleware

Main plan: [plan.md](plan.md)

## Shared contracts

- New config key `require_cache_header` (string, header name) on both `FileCacheMiddleware` and `DefaultProxyRequestHandler` — write-only counterpart to `skip_cache_header`. Only checked against the **response**, never the request.
- Example pairing to use consistently across all docs:
  ```php
  'skip_cache_header'    => 'X-Skip-Cache',   // presence in request OR response → don't cache
  'require_cache_header' => 'X-Cache-Allow',  // absence in response → don't cache (response-only)
  ```
- Combined rule: if both are configured and both headers are found in the response, the response is **not** cached (`skip_cache_header` wins).

## Implementation Steps

### Step 1 — `docs/request-handlers.md`

- Add a `require_cache_header` row to the options table (next to the existing `skip_cache_header` row at line ~60): `| \`require_cache_header\` | \`string\` | No | — | Response header name required for a response to be cached (checked on the response only) |`.
- Add a new "Example: Require a header before caching" section after the existing "Example: Bypass cache with a request header" section (~line 108), showing `require_cache_header` used standalone, plus one combined example showing both `skip_cache_header` and `require_cache_header` together with a short note on the combined-rule behavior.

### Step 2 — `docs/file-cache-middleware-matchers.md`

- Extend the "Overview" section's `skip_cache_header` sentence to also mention `require_cache_header`, e.g.: "You can also set `skip_cache_header` to bypass cache lookup and cache writes when a specific request header is present, or `require_cache_header` to only allow cache writes when a specific header is present in the response."
- Add `require_cache_header` to the "Single Matcher" example block alongside the existing `skip_cache_header` line, so the manual `FileCacheMiddleware` config example stays complete.

### Step 3 — `docs/guides/tent/cache-configuration.md`

- Add a new "Require header before caching" section after the existing "Bypass cache with request header" section, mirroring its style (prose + `Configuration::buildRule` example using `default_proxy`).
- In the "Manual `FileCacheMiddleware` setup" section's example, add `require_cache_header` alongside the existing config keys so the fully-manual (`'type' => 'proxy'`) path is documented too.

### Step 4 — `docs/guides/tent/request-handlers.md`

- Add a `require_cache_header` row to the `DefaultProxyRequestHandler` options table, next to the existing `skip_cache_header` row, matching that table's column style.

### Step 5 — Verify end-to-end

Per this agent's standard verification convention: write a small throwaway rule using `require_cache_header` (and one combining it with `skip_cache_header`) exactly as the new doc examples show, run it against the dev stack or a targeted PHPUnit test, confirm the documented behavior holds, then remove the throwaway code. If any documented example doesn't behave as written, flag the discrepancy to `tent` rather than changing `source/source/lib/` directly.

## Files to Change

- `docs/request-handlers.md` — options table row + new example section.
- `docs/file-cache-middleware-matchers.md` — overview sentence + example block update.
- `docs/guides/tent/cache-configuration.md` — new section + manual-setup example update.
- `docs/guides/tent/request-handlers.md` — options table row.

## CI Checks

None specific to docs-only changes — no doc-lint CI job exists for `docs/`. Run `docker compose run --rm tent_tests composer tests` if Step 5's verification adds/uses a PHPUnit test, to confirm it passes.

## Notes

- This agent's own `.claude/agents/product-dev-extending.md` "Your scope" bullet list predates the `docs/guides/tent/` reorganization (issue #271/#272) and doesn't explicitly list `docs/guides/tent/cache-configuration.md` or `docs/guides/tent/request-handlers.md` — but the coordinating `architect` agent's scope table assigns all of `docs/` (excluding `docs/agents/`) to this agent, and both files already document `skip_cache_header`, so they're in scope for this change. Consider flagging the stale bullet list to `architect` separately (out of scope for this issue).
- Wait for `tent`'s implementation (constructor signatures, exact method names) to land before finalizing example code blocks, though the shape is already fixed by the shared contract above and shouldn't change.
