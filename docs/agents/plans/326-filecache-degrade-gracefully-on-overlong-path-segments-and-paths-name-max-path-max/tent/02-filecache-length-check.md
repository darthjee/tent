# Up-front length check in FileCache

Add an overlong-path check that makes the entry inert before any filesystem access.

**Constants**
- In `FileCache`: `NAME_MAX = 255`, `PATH_MAX = 4096` (bytes; `PATH_MAX` includes the terminating NUL, so a path must be at most `PATH_MAX - 1` bytes).
- In `FileUtils`: expose the length of the suffix `writeAtomically()` adds, e.g. `public const TEMPORARY_SUFFIX_LENGTH = 21` for `'.tmp-' . bin2hex(random_bytes(8))`. Derive the random-byte count in `writeAtomically()` from it, or assert the two stay in sync, so the constant cannot drift.
- `FileCache` headroom for derived files = `FileUtils::TEMPORARY_SUFFIX_LENGTH`, as a named constant, e.g. `DERIVED_SUFFIX_HEADROOM`. It must stay at least as long as every suffix appended to an entry file path. The staleness sentinel (11 bytes) is guarded by a spec in step 05, so that adding a longer derived suffix later cannot silently break the check.

**Check (`hasOverlongPath(): bool`)**
- **Segments**: `explode('/', $this->path . '/' . $method)`. Every segment must satisfy `strlen <= NAME_MAX`; empty segments (from `//`) have length 0 and pass. The location base path is excluded.
- **Leaf names**: `max(strlen(basename(bodyFilePath)), strlen(basename(metaFilePath))) + headroom <= NAME_MAX`. This covers a custom `RequestHasher` returning a long hash.
- **Full path**: `max(strlen(bodyFilePath), strlen(metaFilePath)) + headroom <= PATH_MAX - 1`. The strings are measured exactly as built, so duplicate slashes and the base path count.
- On failure, log once: `cache bypassed — path: <truncated path>, method: <method>, reason: path too long`.
  - Truncation, via a private helper: if the path is longer than 200 bytes, log `substr($path, 0, 200) . '… (' . strlen($path) . ' bytes)'`; otherwise log it as-is.
  - Also truncate the method if it is the overlong part, as it is client-controlled too.

**Constructor order**: `$this->inert = $this->hasUnsafePath() || $this->hasOverlongPath() || (new CacheTrash($location))->contains($this->basePath());`. Short-circuiting guarantees a single warn, with reason `unsafe path`, for a path that is both unsafe and overlong. All checks are lexical, with no filesystem access.

**Docblock**: extend the "Uncacheable entries" section of the `FileCache` class docblock with the overlong case: the limits, what is measured, the headroom and the `warn`. Add the backward-compatibility note: entries written before #324 that sit inside the headroom window become permanent misses, and their files are cleared only by a directory invalidation or a manual purge.

## Files to Change
- `source/source/lib/content/FileCache.php` — constants, `hasOverlongPath()`, truncation helper, constructor order, docblock.
- `source/source/lib/utils/FileUtils.php` — `TEMPORARY_SUFFIX_LENGTH` constant used by `writeAtomically()`.
