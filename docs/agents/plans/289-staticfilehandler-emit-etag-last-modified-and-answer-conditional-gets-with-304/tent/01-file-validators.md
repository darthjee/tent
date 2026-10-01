# File validators
Teach `Tent\Content\File` to expose cache validators computed from file metadata only, without reading the content.

- `etag(): string`: a quoted strong ETag, e.g. `'"' . md5(filesize . '-' . filemtime) . '"'`. Call `clearstatcache(true, $fullPath)` first.
- `lastModified(): int`: the file's mtime (Unix timestamp).
- `lastModifiedHeader(): string`: `gmdate('D, d M Y H:i:s \G\M\T', $mtime)` (IMF-fixdate).
- `validatorHeaders(): array`: `["ETag: <etag>", "Last-Modified: <date>"]`.

Leave `headers()` unchanged. The handler decides whether to append the validators (they are opt-in), so the cache paths that reuse `ResponseContent` are not affected.

## Files to Change
- `source/source/lib/content/File.php`: add the methods above with PHPDoc.
- `source/tests/unit/lib/models/FileTest.php`: test the ETag format, that it is stable for an unchanged file and changes when the file is rewritten or touched with a different mtime (use a temp file plus `touch()`), and the `Last-Modified` format.
