# Plan: Add custom mapping to `CacheCleanupMiddleware`

Issue: [255-add-custom-mapping-to--cachecleanupmiddleware.md](../issues/255-add-custom-mapping-to--cachecleanupmiddleware.md)

## Overview

Add a `custom` configuration key to `CacheCleanupMiddleware` that maps a `:placeholder`-style route
pattern to an explicit list of cache-path templates to clear on a mutating request matching that
pattern. This introduces the first `:placeholder` pattern syntax in the codebase, implemented as a
small, reusable utility, and is purely additive to the existing `collection`/`entity` cleanup — both
run for a matching mutating request.

## Context

`CacheCleanupMiddleware` currently clears cache directories using pure segment-count arithmetic
(`CacheDirResolver`): drop the last segment for `collection`, use the full path for `entity`. This
breaks down for routes like `/games/:game_slug/photo_upload`, where the last segment is an action
(not the entity's own path) and an earlier segment is a non-numeric slug. The fix is a new, independent
mechanism — `custom` — that matches the full request path against configured patterns and clears
concrete, explicitly-listed cache paths built from the captured placeholder values.

All work is scoped to `source/` (the `tent` agent) — this is a pure PHP proxy change, with no
dev-api/frontend/infra involvement.

## Implementation Steps

### Step 1 — Add a `PlaceholderPattern` utility

New class `Tent\Utils\PlaceholderPattern` in `source/source/lib/utils/PlaceholderPattern.php`:

- `public static function match(string $pattern, string $path): ?array`
  Compiles `$pattern` into an anchored regex (splitting on `/`, `preg_quote`-ing literal segments,
  turning `:name` segments into named capture groups) and matches it against `$path`. Returns an
  associative array of `placeholder name => captured value` on match, or `null` when the path does not
  match.
- `public static function substitute(string $template, array $values): string`
  Replaces every `:name` token in `$template` with the corresponding value from `$values` (as produced
  by `match()`).

Placeholder-to-character-class resolution (compiling step), checked against the placeholder name:
- Exactly `slug`, or ending in `_slug` → `[A-Za-z0-9_-]+`
- Exactly `id`, or ending in `_id` → `[0-9]+`
- Exactly `uuid`, or ending in `_uuid` → `[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}`
- Anything else → throw `InvalidArgumentException` at compile time (only these three placeholder
  families are supported, matching the issue spec exactly).

Compile the pattern once per `match()` call (no caching needed — patterns are small and this only runs
on mutating requests).

### Step 2 — Allow resolving/cleaning an arbitrary concrete path

`custom` targets (e.g. `/games.json`, `/games/:game_slug.json` after substitution) are not
`collection`/`entity` targets — they are already concrete full paths, including single-segment ones,
which the existing `CacheDirResolver::resolveEntity()` would reject (it requires ≥ 2 segments).

- `CacheDirResolver`: add `public function resolveExact(string $path): string` — resolves the cache
  `GET/` directory for an arbitrary path with no segment-count restriction, reusing
  `FileUtils::getFullPath()` the same way `resolveEntity()` does.
- `CacheDirCleaner`: add `public function cleanPath(string $path): void` — resolves via
  `resolveExact()` and deletes the directory via the existing private `deleteDir()` (same safety check:
  must be scoped under the configured cache location).

### Step 3 — Wire `custom` into `CacheCleanupMiddleware`

- Constructor gains a new parameter, e.g. `?array $customRules = null` — an associative array of
  `pattern => string[]` (list of target path templates), defaulted to `[]` internally when null.
- `build()`: read `$attributes['custom'] ?? []` and pass it through as-is (already in
  `pattern => target[]` shape from configuration).
- `processRequest()`: keep the existing mutating-method gate and `collection`/`entity` cleanup exactly
  as today, then additionally — still gated on the same mutating-method check — iterate
  `customRules`:
  - For each `pattern => targetTemplates`, call `PlaceholderPattern::match($pattern, $path)`.
  - Skip (continue) when it returns `null`.
  - Otherwise, for each target template, `PlaceholderPattern::substitute($template, $values)` to get a
    concrete path, then `$this->cleaner->cleanPath($concretePath)`.
  - More than one `custom` entry may match the same request; all matches apply (plain loop, no
    early exit).
- Update the class-level PHPDoc example to include a `custom` entry, mirroring the issue's example.

### Step 4 — Update class loading

Add to `source/source/loader.php`:

```php
require_once __DIR__ . '/lib/utils/PlaceholderPattern.php';
```

Placed alongside the other `lib/utils/*.php` requires and before
`lib/middlewares/CacheCleanupMiddleware.php` (dependency-first ordering — matches how `FileUtils.php`
is already required before `CacheDirResolver.php`/`CacheDirCleaner.php`).

### Step 5 — Tests

- New `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternMatchTest.php`:
  - Matches `:slug`/`:xxx_slug`, `:id`/`:xxx_id`, `:uuid`/`:xxx_uuid` placeholders individually and
    combined in one pattern.
  - Returns `null` for a non-matching path (wrong literal segment, wrong character class, wrong
    segment count).
  - Multiple placeholders in one pattern all captured correctly.
  - Throws `InvalidArgumentException` for an unsupported placeholder name (e.g. `:foo`).
- New `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternSubstituteTest.php`:
  - Replaces a single and multiple `:name` tokens with captured values.
  - Leaves the template untouched when it contains no placeholders.
- Extend `source/tests/unit/lib/content/CacheDirResolver/` with a `CacheDirResolverResolveExactTest.php`
  (or add cases to the existing resolve test) covering single-segment and multi-segment paths.
- Extend `source/tests/unit/lib/content/CacheDirCleaner/` (create the directory/test file if it does
  not exist yet — check first) covering `cleanPath()`: deletes an existing dir, no-ops for a
  non-existent one, refuses to delete outside the configured base (mirrors existing `clean()` tests).
- Extend `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareBuildTest.php`:
  parses `custom` from attributes; defaults to no custom rules when omitted.
- Extend `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareProcessRequestTest.php`:
  - A mutating request matching a `custom` pattern clears the substituted target path(s).
  - `custom` is additive — both the default/explicit `collection`/`entity` targets and the matched
    `custom` targets are cleared for the same request.
  - A non-mutating request (`GET`) does not trigger `custom` cleanup.
  - A request path that does not match any `custom` pattern leaves those targets untouched.
  - Multiple `custom` patterns matching the same request all apply.
  - Use the issue's own example (`/games/:game_slug/photo_upload` → `/games.json`,
    `/games/:game_slug.json`) as at least one of the scenarios, to keep the test aligned with the
    documented configuration shape.

### Step 6 — Update developer documentation

Update the `CacheCleanupMiddleware` section in `docs/creating-middlewares.md` to document the new
`custom` key: the config shape, the three supported placeholder families and their character classes,
and the additive behavior relative to `clear`. Mirror the issue's example configuration.

## Files to Change

- `source/source/lib/utils/PlaceholderPattern.php` — new placeholder-pattern compile/match/substitute utility
- `source/source/lib/content/CacheDirResolver.php` — add `resolveExact()`
- `source/source/lib/content/CacheDirCleaner.php` — add `cleanPath()`
- `source/source/lib/middlewares/CacheCleanupMiddleware.php` — add `custom` support, update PHPDoc
- `source/source/loader.php` — require the new utility class
- `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternMatchTest.php` — new
- `source/tests/unit/lib/utils/PlaceholderPattern/PlaceholderPatternSubstituteTest.php` — new
- `source/tests/unit/lib/content/CacheDirResolver/` — new/extended test(s) for `resolveExact()`
- `source/tests/unit/lib/content/CacheDirCleaner/` — new/extended test(s) for `cleanPath()`
- `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareBuildTest.php` — extend for `custom`
- `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareProcessRequestTest.php` — extend for `custom`
- `docs/creating-middlewares.md` — document the `custom` key

## CI Checks

- `source`: `composer coverage` (CI job: `unit_test`)
- `source`: `composer lint` (CI job: `checks`)

## Notes

- No caching of compiled patterns — `custom` rules are expected to be small in number and this only
  runs on mutating requests, so re-compiling per request is not a performance concern.
- Placeholder support is intentionally limited to the three families named in the issue (`slug`, `id`,
  `uuid`, matched exactly or by `_slug`/`_id`/`_uuid` suffix); anything else is a configuration error
  and throws `InvalidArgumentException`, consistent with how `RegexRequestMatcher::build()` validates
  its `pattern` attribute today.
- `CacheDirCleaner` may not currently have its own dedicated test directory (only `CacheDirResolver`
  does per the initial exploration) — verify during implementation and create
  `source/tests/unit/lib/content/CacheDirCleaner/` if missing, following the same per-method test file
  convention used for `CacheDirResolver`.
