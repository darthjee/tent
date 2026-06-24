# Cache Cleanup Middleware

## Context

The existing `FileCacheMiddleware` caches GET responses on disk at
`{location}/{path}/{method}/{queryHash}.body.dat`. There is no invalidation mechanism:
stale entries persist indefinitely after mutating requests. When a client sends
`POST /users`, the cached `GET /users` response is served as-is on the next request,
returning outdated data.

## What needs to be done

Implement `CacheCleanupMiddleware` — a new middleware that deletes relevant cache
directories when a mutating request (POST, PATCH, PUT, DELETE) is received, before
forwarding the request upstream.

**Source (`source/`):**

- Create `Tent\Middlewares\CacheCleanupMiddleware` implementing the `Middleware` interface.
- The middleware acts in `processRequest`, resolving which directories to delete based
  on the request method and path.
- Cache files are organized as `{location}/{path}/{method}/`, so clearing `GET /users`
  means removing the directory `{location}/users/GET/`.
- Default cleanup targets (configurable via `clear`):
  - `collection` → `{location}/{basePath}/GET/`
  - `entity` → `{location}/{basePath}/{id}/GET/` (for entity paths like `/users/1`)
- Default when `clear` is omitted: `['collection']` for POST; `['collection', 'entity']`
  for PATCH, PUT, DELETE.
- Implement `static build(array $attributes)` to support configuration-driven instantiation.
- Log each deletion for debugging (via `Logger::debug`).
- Must not touch unrelated cache directories.

**Configuration example:**

```php
Configuration::buildRule([
  'handler'     => [...],
  'matchers'    => [...],
  'middlewares' => [
    [
      'class'    => 'Tent\\Middlewares\\CacheCleanupMiddleware',
      'location' => './cache',
      'clear'    => ['collection', 'entity'],
    ]
  ]
]);
```

**Tests (`source/tests/`):**

- Unit tests for `CacheCleanupMiddleware`:
  - Default cleanup targets per HTTP method.
  - Custom `clear` configuration.
  - No side-effects on unrelated cache directories.

## Acceptance criteria

- [ ] `CacheCleanupMiddleware` activates only on POST, PATCH, PUT, DELETE.
- [ ] Default logic clears collection cache on POST; collection + entity on PATCH/PUT/DELETE.
- [ ] Custom `clear` values can be set per rule via configuration.
- [ ] Cache cleanup happens in `processRequest`, before upstream forwarding.
- [ ] Unrelated cache entries are not affected.
- [ ] Logger records each directory deletion.
- [ ] Unit tests pass for all default and custom scenarios.
