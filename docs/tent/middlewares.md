# Middlewares

Middlewares sit between the incoming request and the handler. Each middleware can:

- **Modify the request** before it reaches the handler (change headers, rewrite paths, serve from cache).
- **Modify the response** before it is sent to the client (add headers, cache to disk).

Middlewares are applied in the order they appear in the configuration array.

---

## `FileCacheMiddleware`

Caches upstream responses to disk and serves them on subsequent identical requests, bypassing the backend entirely.

```php
[
    'class' => 'Tent\Middlewares\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\Matchers\StatusCodeMatcher',
            'httpCodes' => ['2xx']
        ]
    ]
]
```

### Cache matchers

Matchers inside `FileCacheMiddleware` control which responses are stored. **All** matchers must pass for a response to be cached (logical AND).

**`StatusCodeMatcher`** — match by HTTP status code:

```php
// Cache exact codes
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => [200, 301]]

// Cache any 2xx response
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => ['2xx']]

// Cache 2xx and redirects
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => ['2xx', 301, 302]]
```

**`RequestMethodMatcher`** — match by HTTP method:

```php
// Only cache GET and HEAD requests
['class' => 'Tent\Matchers\RequestMethodMatcher', 'requestMethods' => ['GET', 'HEAD']]
```

### Cache file structure

Cache files are named from a hash of the request path. Each unique URI maps to a unique cache file. The `location` directory is created automatically if it does not exist.

> **Note**: There is no built-in cache expiry. To clear the cache, delete the files in the `location` directory.

---

## `CacheCleanupMiddleware`

Deletes stale `FileCacheMiddleware` cache directories when a mutating request (`POST`, `PATCH`, `PUT`, `DELETE`) arrives, before the request is forwarded upstream. Use it alongside `FileCacheMiddleware` so writes don't leave outdated `GET` responses cached.

```php
[
    'class'    => 'Tent\Middlewares\CacheCleanupMiddleware',
    'location' => './cache',
    'clear'    => ['collection', 'entity'],
    'custom'   => [
        '/games/:game_slug/photo_upload' => [
            '/games.json',
            '/games/:game_slug.json',
        ]
    ]
]
```

- **`location`** (required) — must match the `location` used by the corresponding `FileCacheMiddleware`.
- **`clear`** (optional) — which cache directories to delete. Defaults to `['collection']` on `POST`, and `['collection', 'entity']` on `PATCH`, `PUT`, `DELETE`.
  - `collection` — the parent-resource cache dir, e.g. a write to `/users/1` clears `{location}/users/GET/`.
  - `entity` — the cache dir for the specific resource path, e.g. `{location}/users/1/GET/`. Has no effect on single-segment paths (e.g. `/users`).
- **`custom`** (optional) — maps a `:placeholder` route pattern to an explicit list of cache path templates to clear when a mutating request matches it. On a match, the captured placeholder values are substituted into every target template (e.g. the actual `game_slug` value fills in `:game_slug` in `/games/:game_slug.json`) before that concrete path's cache is cleared. More than one `custom` pattern may match the same request, and all matches apply. `custom` is additive — it never replaces `clear`'s `collection`/`entity` cleanup; both run for a matching mutating request.
  - Supported placeholders (matched exactly or by suffix):
    - `:slug` or `:xxx_slug` — letters, digits, dashes, underscores (`[A-Za-z0-9_-]+`).
    - `:id` or `:xxx_id` — digits only (`[0-9]+`).
    - `:uuid` or `:xxx_uuid` — canonical UUID shape (`[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}`).
  - Any other placeholder name is a configuration error.
  - In the example above, a `POST /games/space-invaders/photo_upload` clears `{location}/games.json/GET/` and `{location}/games/space-invaders.json/GET/`, in addition to whatever `clear` targets apply.

Place it **before** the handler so the cleanup happens ahead of the upstream call, allowing `FileCacheMiddleware` to re-cache the fresh response afterwards.

---

## `SetHeadersMiddleware`

Injects or overrides request headers before the request is forwarded to the backend.

```php
[
    'class' => 'Tent\Middlewares\SetHeadersMiddleware',
    'headers' => [
        'Host'            => 'backend.internal',
        'X-Custom-Header' => 'value'
    ]
]
```

Common uses: setting `Host`, injecting auth tokens, adding routing headers.

---

## `RenameHeaderMiddleware`

Copies the value of one request header to a new name and removes the original.

```php
[
    'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
    'from'  => 'Host',
    'to'    => 'X-Forwarded-Host'
]
```

This is typically paired with `SetHeadersMiddleware`: first preserve the original header, then overwrite it with the correct upstream value. `default_proxy` does this pair automatically.

---

## `SetPathMiddleware`

Rewrites the request path before it reaches the handler.

```php
[
    'class' => 'Tent\Middlewares\SetPathMiddleware',
    'path' => '/index.html'
]
```

Primarily used with `StaticFileHandler` to map `/` to `/index.html` for single-page applications.

---

## `RedirectMiddleware`

Rewrites the request path with a regex replacement and returns a `302` response immediately.

```php
[
    'class' => 'Tent\Middlewares\RedirectMiddleware',
    'pattern' => '/^\/old\/(.*)$/',
    'replacement' => '/new/$1'
]
```

If the regex matches, the middleware sets a `Location` header and short-circuits handler execution.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
