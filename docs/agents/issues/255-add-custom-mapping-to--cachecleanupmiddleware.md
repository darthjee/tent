# Issue: Add custom mapping to `CacheCleanupMiddleware`

## Description
Right now, `CacheCleanupMiddleware` is simple: on a mutating request (`POST`, `PUT`, `PATCH`, `DELETE`), it clears the cache directory for the request's `collection` and/or `entity` targets, purely by counting URL path segments (dropping the last segment for `collection`, using the full path for `entity`). There is no regex/id/uuid/slug validation of the last segment today — it's just segment-count arithmetic (see `CacheDirResolver`).

```php
[
    'class'    => 'Tent\Middlewares\CacheCleanupMiddleware',
    'location' => './cache',
    'clear'    => ['collection', 'entity']
]
```

This is also the first place in the codebase that would need a `:placeholder` route-pattern syntax — existing route matching (`RegexRequestMatcher`) uses raw regex, not named placeholders.

## Problem
This breaks down for custom routes such as `/games/:game_slug/photo_upload`:
- `game_slug` is not an id, so the segment-count logic does not associate this path with the `/games/:game_slug` entity.
- `photo_upload` is an action nested under the entity, not the entity's own path, so it is not grouped with `/games/:game_slug` updates at all.

## Solution
Add a new `custom` configuration key to `CacheCleanupMiddleware`, mapping a route pattern to an explicit list of cache paths to clear on a mutating request to that route:

```php
[
    'class'    => 'Tent\Middlewares\CacheCleanupMiddleware',
    'location' => './cache',
    'clear'    => ['collection', 'entity'],
    'custom' => [
        '/games/:game_slug/photo_upload' => [
            '/games.json',
            '/games/:game_slug.json',
        ]
    ]
]
```

Pattern placeholders and their matched character classes:
- `:slug` or `:<anything>_slug` — letters, digits, dashes, underscores (`[A-Za-z0-9_-]+`).
- `:id` or `:<anything>_id` — digits only (`[0-9]+`).
- `:uuid` or `:<anything>_uuid` — canonical UUID shape (`[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}`).

Behavior:
- On a mutating request (`POST`/`PUT`/`PATCH`/`DELETE`), the request path is checked against every configured `custom` pattern (in definition order). Each pattern that matches has its captured placeholder values substituted into its target paths (e.g. the actual `game_slug` value fills in `:game_slug` in `/games/:game_slug.json`), and each resulting concrete path's cache is cleared.
- More than one `custom` entry may match the same request; all matches apply.
- `custom` is additive: it does not replace the existing `collection`/`entity` clearing configured via `clear` — both run for a matching mutating request.
