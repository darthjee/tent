# Plan: Fix PlaceholderPattern when there are suffixes

Issue: [257-fix-placeholderpattern-when-there-are-suffixes.md](../issues/257-fix-placeholderpattern-when-there-are-suffixes.md)

## Overview

`PlaceholderPattern::compileSegment()` currently treats everything after the leading `:` as the placeholder name, so a last segment like `:character_id.json` resolves to the name `character_id.json`, which neither matches a known character-class suffix (`_slug`/`_id`/`_uuid`) nor is valid inside a PHP named capture group. This plan adds support for an optional trailing format suffix (e.g. `.json`, `.xml`) on the **last** segment of the pattern only, stripping it from the placeholder name before resolving the character class, and re-appending it to the compiled regex as a literal outside the capture group.

## Context

- `source/source/lib/utils/PlaceholderPattern.php` compiles `:name` route segments into named regex capture groups (`compile()` / `compileSegment()` / `characterClassFor()`).
- `characterClassFor()` throws `InvalidArgumentException` when a name doesn't end in a recognized suffix (`_slug`, `_id`, `_uuid`) or equal one of `slug`/`id`/`uuid`.
- `substitute()` already handles trailing suffixes correctly, because its regex (`/:([A-Za-z0-9_]+)/`) stops matching at the `.`, so no change is needed there.
- `CacheCleanupMiddleware`'s `custom` mapping (`source/source/lib/middlewares/CacheCleanupMiddleware.php`) calls `PlaceholderPattern::match()` with patterns that can include a format suffix on the last segment, which currently throws instead of matching.
- Only the **last** path segment is expected to carry a trailing format suffix; mid-path segments should behave exactly as they do today.

## Implementation Steps

### Step 1 — Detect and strip a trailing format suffix on the last segment

In `compile()`, when iterating segments with `array_map`, the last segment needs different handling than the others. Refactor so the last segment is compiled through a variant of `compileSegment()` (or an added parameter/helper) that:
1. Checks whether the segment (after stripping the leading `:`) matches a trailing literal suffix pattern — a `.` followed by one or more `[A-Za-z0-9]` characters at the very end of the segment, e.g. via a regex like `/^(.*)(\.[A-Za-z0-9]+)$/` applied to the placeholder name portion.
2. If it matches, use the part before the suffix as the placeholder `$name` for `characterClassFor()` and the named capture group, and keep the suffix (e.g. `.json`) to be appended, `preg_quote()`-d, after the closing `)` of the capture group — outside the capture.
3. If it does not match, behaves exactly as before.

Keep this logic isolated (e.g. a small private helper like `splitFormatSuffix(string $name): array` returning `[$baseName, $literalSuffix]`) so `compileSegment()` stays readable and the "last segment only" rule is applied at the call site in `compile()`, not inside `compileSegment()` itself (which has no notion of "last").

### Step 2 — Wire the suffix handling only for the last segment

Update `compile()` so only the final element of `$segments` is compiled with format-suffix awareness; all other placeholder segments keep calling `compileSegment()` unchanged. Non-placeholder (literal) segments are untouched regardless of position.

### Step 3 — Tests

Add test cases to `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternMatchTest.php` covering:
- `PlaceholderPattern::match('/games/:game_slug/npcs/:character_id.json', '/games/space-invaders/npcs/42.json')` returns `['game_slug' => 'space-invaders', 'character_id' => '42']`.
- A different suffix (e.g. `.xml`) also works, proving the suffix isn't hardcoded to `.json`.
- A mismatched suffix in the path (e.g. pattern expects `.json`, path has `.xml`, or no suffix at all) returns `null`.
- A placeholder with a format suffix that isn't the last segment is **not** specially treated (suffix handling is last-segment only) — confirm existing non-suffix behavior for mid-path segments is unaffected (may already be covered by existing tests; add one only if not).
- Existing tests in this file and in `PlaceholderPatternSubstituteTest.php` must continue passing unmodified, confirming no regression.

### Step 4 — Run the full local dev cycle

Run lint and tests locally from `source/` before committing:
```
cd source && composer lint && composer tests:unit
```

## Files to Change

- `source/source/lib/utils/PlaceholderPattern.php` — add format-suffix detection/stripping for the last segment in `compile()`, plus a small helper to split name/suffix; keep `compileSegment()` and `characterClassFor()` otherwise unchanged.
- `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternMatchTest.php` — add coverage for the new format-suffix behavior described above.

## CI Checks

- `source`: `composer coverage` (CI job: `unit_test`)
- `source`: `composer lint` (CI job: `checks`)

## Notes

- Do not hardcode `.json`; the suffix regex must accept any `.` followed by extension characters (e.g. `[A-Za-z0-9]+`).
- `substitute()` requires no change — verified its regex already stops at `.` and behaves correctly today.
- Keep the fix scoped to `PlaceholderPattern`; no changes to `CacheCleanupMiddleware` are needed since it only depends on `match()`'s public contract, which stays the same shape (`array<string,string>|null`).
