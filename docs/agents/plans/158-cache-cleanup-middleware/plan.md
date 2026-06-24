# Plan: Cache Cleanup Middleware

Issue: [158-cache-cleanup-middleware.md](../issues/158-cache-cleanup-middleware.md)

## Overview

Add `CacheCleanupMiddleware` to `source/` — a new middleware that deletes stale file-cache
directories when a mutating request (POST, PATCH, PUT, DELETE) arrives, before the request
is forwarded upstream. This is a pure `source/` change; only the `tent` agent is involved.

## Context

`FileCacheMiddleware` stores GET responses at `{location}/{path}/GET/{queryHash}.{ext}`.
There is no invalidation path: stale entries live forever. `CacheCleanupMiddleware` resolves
this by deleting the relevant `GET/` directories on write operations, using the same
`location` directory as `FileCacheMiddleware`.

Cache path anatomy (from `FileCache::basePath()`):
```
{location}/{requestPath}/{requestMethod}/{queryHash}.body.dat
```
So clearing `GET /users` means deleting the directory `{location}/users/GET/`.

## Implementation Steps

### Step 1 — Create `CacheCleanupMiddleware`

Create `source/source/lib/middlewares/CacheCleanupMiddleware.php`.

- Extend `Tent\Middlewares\Middleware`.
- Constructor: `__construct(FolderLocation $location, ?array $clearTargets = null)`
  - `$clearTargets` is `null` when using method-driven defaults.
- `static build(array $attributes)`: read `location` and optional `clear` key.
- `processRequest(ProcessingRequest $request)`:
  1. Check request method. If not in `[POST, PATCH, PUT, DELETE]`, return early.
  2. Resolve effective targets:
     - If `$clearTargets` is set, use it.
     - Else: `POST` → `['collection']`; `PATCH/PUT/DELETE` → `['collection', 'entity']`.
  3. For each target, resolve which directory to delete:
     - `collection`: `{location}/{basePath}/GET/` where `basePath` = path without last segment
       (e.g. `/users/1` → collection dir is `{location}/users/GET/`).
       For root-level collection paths like `/users`, `basePath` is just `/users`.
     - `entity`: `{location}/{requestPath}/GET/` — the full request path directory
       (e.g. `{location}/users/1/GET/`). Skip if the request path has no numeric/id segment.
  4. Delete each resolved directory recursively if it exists.
  5. Log each deletion: `Logger::debug('cache cleared — dir: ' . $dir)`.
- `processResponse` is not overridden (no-op from parent).

Directory path construction follows the same logic as `FileCache::basePath()`:
```php
FileUtils::getFullPath($this->location->basePath(), trim($path, '/'), 'GET')
```

For `collection`, strip the last path segment:
```php
$segments = explode('/', trim($path, '/'));
array_pop($segments);
$collectionPath = implode('/', $segments);
// Then: FileUtils::getFullPath($location->basePath(), $collectionPath, 'GET')
```

### Step 2 — Register in loader

Add `CacheCleanupMiddleware` to `source/source/loader.php` alongside the other middleware
includes, so it is autoloaded by the proxy.

### Step 3 — Write unit tests

Create `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/` with:

- `CacheCleanupMiddlewareBuildTest.php`:
  - `build` with only `location` → instance created.
  - `build` with `clear` attribute → stored correctly.

- `CacheCleanupMiddlewareProcessRequestTest.php`:
  - GET request → no directories deleted, request returned unchanged.
  - POST `/users` → `{location}/users/GET/` deleted; entity dir untouched.
  - POST `/users` with explicit `clear: ['entity']` → entity dir deleted, not collection.
  - PATCH `/users/1` → both `{location}/users/GET/` and `{location}/users/1/GET/` deleted.
  - DELETE `/users/1` → same as PATCH.
  - PUT `/users/1` → same as PATCH.
  - Mutating request when cache dir does not exist → no error (silent no-op).
  - Unrelated cache directories not deleted.
  - Logger called for each deleted directory.

Follow the same setUp/tearDown pattern as `FileCacheMiddlewareProcessRequestTest`:
- Create a temp dir via `sys_get_temp_dir() . '/cache_cleanup_test_' . uniqid()`.
- Tear down with `FileSystemUtils::removeDirRecursive`.
- Silence logger with `NullLoggerInstance` in setUp, restore in tearDown.

## Files to Change

- `source/source/lib/middlewares/CacheCleanupMiddleware.php` — new file
- `source/source/loader.php` — add require for the new class
- `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareBuildTest.php` — new file
- `source/tests/unit/lib/middlewares/CacheCleanupMiddleware/CacheCleanupMiddlewareProcessRequestTest.php` — new file

## CI Checks

- `source/`: `docker compose run --rm tent_tests composer tests:unit` (CI job: `unit-tests`)
- `source/`: `docker compose run --rm tent_tests composer lint` (CI job: `lint`)

## Notes

- The `entity` target only makes sense for paths with at least two segments (e.g. `/users/1`).
  For a root-level POST like `/users`, the entity path would be identical to the request path
  itself (`{location}/users/GET/`), which is the same as `collection` — skip it to avoid a
  double-delete rather than computing it.
- Recursive directory deletion should be isolated to subdirectories of `location` to avoid
  accidental deletion of unrelated paths. Validate the resolved dir starts with `location->basePath()`.
- `processResponse` is deliberately left as the parent no-op. Cleanup happens before the upstream
  call, so the freshly fetched response can be re-cached by a co-configured `FileCacheMiddleware`.
