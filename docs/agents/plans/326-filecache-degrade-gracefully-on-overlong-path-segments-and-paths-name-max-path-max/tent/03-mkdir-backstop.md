# mkdir backstop

Make `ensureCacheFolderExists()` never emit a PHP warning, and report failures through `Logger`. This covers what the up-front check cannot predict: filesystems with lower limits (e.g. eCryptfs, about 143 bytes per name), permission denied, a full disk, a read-only filesystem, or a regular file standing where a directory is needed.

- Call `@mkdir($basePath, 0755, true)`. If it fails and `!is_dir($basePath)` (which tolerates concurrent creation by another request), log:

  `cache store skipped — path: <path>, method: <method>, reason: <error_get_last()['message'] ?? 'mkdir failed'>`

  `error_clear_last()` can be called before the `mkdir` so a stale message is never reported.
- Make it return `bool`, and let `prepareCacheFolder()` use the result. The existing `is_dir($basePath) && $this->isContained($basePath)` post-check stays.
- Update the comment `// A failed creation has already been reported by mkdir().` and the method docblock.
- No truncation is needed here: the path already passed the length check, so it is bounded.

## Files to Change
- `source/source/lib/content/FileCache.php` — `ensureCacheFolderExists()` and `prepareCacheFolder()`.
