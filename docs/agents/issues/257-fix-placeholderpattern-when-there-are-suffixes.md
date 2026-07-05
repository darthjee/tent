# Fix PlaceholderPattern when there are suffixes

## Context

`PlaceholderPattern` (`source/source/lib/utils/PlaceholderPattern.php`) compiles `:placeholder` route segments into named regex capture groups. When a route pattern's final segment includes a trailing format suffix, such as `:character_id.json` in `/games/:game_slug/npcs/:character_id.json`, the format suffix is incorrectly treated as part of the placeholder name.

`compileSegment()` takes everything after the leading `:` as the placeholder name, so for `:character_id.json` the resolved name is `character_id.json` instead of `character_id`. This breaks in two ways:

1. `characterClassFor()` checks the name's suffix (`_slug`, `_id`, `_uuid`) to pick a regex character class. `character_id.json` does not end with `_id`, so it falls through and throws `InvalidArgumentException("Unsupported placeholder ':character_id.json'.")`.
2. Even if that check were bypassed, `.` is not a valid character in a PHP named capture group (`(?P<name>...)`), so the compiled regex would fail outright.

This is used by `CacheCleanupMiddleware`'s `custom` mapping (`source/source/lib/middlewares/CacheCleanupMiddleware.php`), which calls `PlaceholderPattern::match()` against the incoming request path. Any `custom` route pattern with a placeholder immediately followed by a format suffix currently throws instead of matching.

Note: `PlaceholderPattern::substitute()` is unaffected — its regex (`/:([A-Za-z0-9_]+)/`) already stops at the `.`, so target templates like `/games/:game_slug.json` substitute correctly today.

## What needs to be done

In `PlaceholderPattern::compileSegment()` (or `compile()`), when compiling the last segment of the pattern, detect an optional trailing format suffix (a literal `.` followed by extension characters) before resolving the placeholder name. Use the part before the suffix as the placeholder name for character-class resolution and the named capture group, and append the suffix back into the compiled regex as a literal (`preg_quote`-d), outside the capture group.

- The placeholder name should resolve to `character_id` (used for both character-class selection and the captured value key), not `character_id.json`.
- The literal `.json` suffix should be matched as a literal part of the regex, not included in the captured value.
- The format suffix must be handled generically (`.json`, `.xml`, `.csv`, etc. — any `.` followed by extension characters), not hardcoded to `.json` specifically.
- Only the last segment of a route pattern needs to support a trailing format suffix; a format suffix is not expected mid-path on other segments.
- Keep `match()` consistent with `substitute()`, which already handles this case correctly.

## Acceptance criteria

- [ ] `PlaceholderPattern::match('/games/:game_slug/npcs/:character_id.json', '/games/space-invaders/npcs/42.json')` returns `['game_slug' => 'space-invaders', 'character_id' => '42']` instead of throwing.
- [ ] A route pattern segment like `:character_id.json` compiles without error, with the placeholder name resolved to `character_id`.
- [ ] The format suffix is generic (not hardcoded to `.json`), supporting any literal `.` followed by extension characters.
- [ ] `CacheCleanupMiddleware` `custom` mappings can target routes whose final segment has a format suffix without throwing.
- [ ] Existing behavior for patterns without a trailing format suffix is unchanged.

---
Tags: :shipit:
