# Reserved-path guard in FileCache

`FileCache` is the single place that builds cache read and write paths for `FileCacheMiddleware` and `BackgroundRefresher`. Make it treat any entry whose resolved directory lies inside the trash as uncacheable, so a request like `GET /.tent-trash/<entry>` can never create `<base>/.tent-trash/<entry>/GET/` inside a tree being deleted.

- In the constructor, compute `$this->reserved = (new CacheTrash($location))->contains($this->basePath())` once.
- `exists()` returns `false` when the entry is reserved.
- `store()` returns early when it's reserved, before `ensureCacheFolderExists()`, so it does no `mkdir` and writes no files.
- `remove()` does nothing when it's reserved.
- `content()`, `headers()` and `httpCode()` are only reached after `exists()`, so they need no change. Confirm that by reading `FileCacheMiddleware`.
- Client-visible behavior: a request on a reserved path is proxied normally and never cached. There is no 4xx and no change to the middleware.
- Update the class docblock to mention the reserved trash folder.

Tests:
- `FileCache` with request paths `/.tent-trash/x`, `/.TENT-TRASH/x`, `/./.tent-trash/x` and `//.tent-trash/x`: `exists()` is `false` even when matching files were planted on disk, `store()` creates no directory or file, and `remove()` leaves planted files untouched.
- `/users/.tent-trash` is cached normally (store then exists).
- `FileCacheMiddleware` level: a `GET /.tent-trash/x` passes through to the handler and, after the response, nothing exists under `<base>/.tent-trash/`.

## Files to Change
- `source/source/lib/content/FileCache.php` — reserved flag and guards in `exists()`, `store()` and `remove()`, plus the docblock.
- `source/tests/unit/lib/models/FileCache/FileCacheReservedPathTest.php` — new.
- `source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareReservedPathTest.php` — new (or an added case in the existing process-request/response tests, whichever matches local conventions better).
