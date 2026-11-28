# Extract the containment helper

Move the realpath comparison out of `CacheDirCleaner::resolvesInsideBase()` into a public `FolderLocation::contains(string $path): bool` (see the contract in `plan.md`): `clearstatcache(true)`, resolve both `$path` and `basePath()` with `realpath()`, return `false` if either fails, `true` when equal or when the resolved path starts with `<realBase>/`. No logging.

Make `CacheDirCleaner::resolvesInsideBase()` delegate to it (`$this->location->contains(dirname($dir))`), keeping its own `refuse()` logging and log text unchanged, so the #320 specs keep passing as-is. Update the `FolderLocation` class docblock: it is now also used for the cache location.

## Files to Change

- `source/source/lib/models/FolderLocation.php` — add `contains()`.
- `source/source/lib/content/CacheDirCleaner.php` — delegate the comparison to `FolderLocation::contains()`.
- `source/tests/unit/lib/models/FolderLocationTest.php` (or a new `FolderLocation/FolderLocationContainsTest.php`) — specs: path equal to base, path inside, path outside, prefix look-alike (`/tmp/x/cache-other` vs `/tmp/x/cache`), unresolvable path, base itself a symlink, intermediate symlink pointing outside.
