# Product-Dev-Extending Plan: Add domain in the matchers

Main plan: [plan.md](plan.md)

## Shared contracts

- The `domain` config key is optional, a bare hostname (no port), accepted alongside `method`/`uri`/`type` (and `pattern` for `regex`) in any `RequestMatcher` config entry.
- `%` is the wildcard character with SQL `LIKE` semantics — any sequence of characters, anywhere in the pattern, any number of times (e.g. `'%.mydomain.com'`).
- Omitting `domain` matches any domain — existing examples in the docs remain valid as-is.
- This depends on `tent`'s implementation (see [tent.md](tent.md)) landing first, or at least the exact config shape being final, before finalizing the doc example.

## Implementation Steps

### Step 1 — Update `docs/adding-request-matchers.md`

Add a `domain` example to the "Configuration example" section, showing both a plain domain and a wildcard domain, consistent with the shared contract above:

```php
'matchers' => [
    ['method' => 'GET', 'uri' => '/persons', 'type' => 'begins_with', 'domain' => 'mydomain.com'],
    ['method' => 'GET', 'uri' => '/persons', 'type' => 'begins_with', 'domain' => '%.mydomain.com'],
],
```

Add a short prose note next to the example explaining: `domain` is optional, matched case-insensitively, ignores the incoming request's port, and supports `%` as an any-sequence wildcard (SQL `LIKE` semantics).

### Step 2 — Verify the example end-to-end

Per this agent's usual verification process: wire the new `domain` example into a throwaway rule (or an existing PHPUnit test under `source/tests/`, coordinating with `tent` if a permanent fixture is warranted) and confirm it actually matches/rejects requests as documented, using the real `RequestMatcher::build()`/`matches()` once `tent`'s implementation lands. Remove any throwaway verification code afterwards.

### Step 3 — Check other docs for staleness

Skim `docs/HOW_TO_USE_DARTHJEE-TENT.md` and `docs/file-cache-middleware-matchers.md` for any matcher configuration examples that would benefit from mentioning `domain` is now available, or that could be misread as exhaustive. Only touch them if there's an actual gap — no need to add `domain` everywhere.

## Files to Change

- `docs/adding-request-matchers.md` — add `domain` (incl. wildcard) to the configuration example and explain its semantics.

## Notes

- Do not change `source/source/lib/` — if the real interface doesn't match what's documented here once `tent` lands its change, flag it back to `tent` rather than working around it in the docs.
- No CI job lints these docs; verification is manual per Step 2 above.
