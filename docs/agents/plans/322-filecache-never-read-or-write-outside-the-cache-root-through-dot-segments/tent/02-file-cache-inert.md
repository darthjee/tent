# FileCache: inert on unsafe path or method
Rename `FileCache::$reserved` to `$inert` and set it in the constructor when the entry resolves inside the trash (today's `CacheTrash::contains()` check) **or** when `FileUtils::hasSafeSegments($this->path . '/' . $request->requestMethod())` is false. In the unsafe case, log `Logger::warn('cache bypassed — path: <path>, method: <method>, reason: unsafe path')`. Every existing `$this->reserved` guard (`timestamp()`, `exists()`, `store()`, `remove()`) switches to `$this->inert`; make sure no other method reaches the filesystem for an inert entry.

Update the class docblock: rename the "Reserved trash folder" section to cover both causes (trash entry, or a request path/method with a `.`/`..` segment or NUL byte) and explain that such an entry is uncacheable — never a hit, nothing stored or removed, no directory created — while the request itself is proxied normally. Update the property docblock accordingly.

Tests (new `FileCacheUnsafePathTest.php`, using a temporary cache root inside a temporary parent directory so a sibling of the root can be inspected):
- For `/../x`, `/a/../../x`, `/a/./b` and the method `..` on `/`: `exists()` is false, `timestamp()` is null, and `store()` creates no file or directory inside the root nor in its parent/sibling directory.
- A body/meta pair planted at the traversal target outside the root is never reported as a hit.
- `remove()` leaves a planted file at the traversal target untouched.
- A `warn` line containing `reason: unsafe path` is logged (use the logging test helpers already used by the `CacheDirCleaner` specs).
- Safe look-alikes (`/...`, `/..foo`, `/a..b`) are still stored and read back.
- `FileCacheReservedPathTest.php` keeps passing (adapt only for the rename if it touches internals).

## Files to Change
- `source/source/lib/content/FileCache.php` — `$reserved` → `$inert`, unsafe-path check and `warn`, docblocks.
- `source/tests/unit/lib/models/FileCache/FileCacheUnsafePathTest.php` — new.
- `source/tests/unit/lib/models/FileCache/FileCacheReservedPathTest.php` — only if it relies on the old name.
