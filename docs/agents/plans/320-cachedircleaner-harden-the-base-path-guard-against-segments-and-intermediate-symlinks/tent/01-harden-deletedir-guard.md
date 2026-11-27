# Harden the deleteDir() guard

Replace the inline prefix check in `CacheDirCleaner::deleteDir()` with a private method (e.g. `isSafelyScoped(string $dir): bool`) that runs before the existing trash check and #316's `is_link()` handling. Final check order, all before any filesystem mutation:

1. **Misconfigured base**: if `rtrim($this->location->basePath(), '/') === ''` (base is `/`), refuse with `warn`, reason `misconfigured cache location`.
2. **Prefix check** (existing): `$dir` must start with `<base>/`, otherwise refuse with `warn`, reason `outside cache location`.
3. **Segment check** (string-only): take the remainder of `$dir` after `<base>/`, split on `/`, ignore empty segments, and refuse with `warn`, reason `dot segment`, if any segment is exactly `.` or `..`, or if the remainder contains `"\0"`. Match whole segments only — `...`, `..foo`, `foo..`, `%2e%2e` are accepted. Only the remainder is inspected, since the base may legitimately contain `.`/`..` (e.g. `./cache`).
4. **`realpath` check**: compute `realpath($base)` and `realpath(dirname($dir))`.
   - Either is `false` (base or parent absent) → return silently (no log): nothing to delete, same observable outcome as today.
   - Accept when the real parent equals the real base, or starts with `<real base> . '/'` (exact, case-sensitive comparison). `cleanPath('/')` (`$dir` = `<base>/GET`) is accepted through the "equals" case.
   - Otherwise refuse with `warn`, reason `resolves outside cache location (<real parent>)`.
   - Never resolve the final component — a symlinked root still passes and is unlinked by `is_link()`.
5. Existing `$this->trash->contains($dir)` check stays silent and unchanged.

Warn format, consistent with the existing `cache trash rename failed — dir: …, reason: …` lines:

```
cache cleanup refused — dir: <dir>, reason: <reason>
```

Update docblocks:

- The private guard method: describe each check, the silent cases, and the documented TOCTOU limitation (a component swapped for a symlink between the check and `rename()` is not prevented; PHP offers no `openat()`-style traversal, and exploiting it requires write access inside the cache directory).
- `deleteDir()`'s docblock and the class-level docblock (add a short "## Safety" subsection, or extend "Concurrency and failures"): only paths genuinely under the configured cache location are touched; `.`/`..` segments and intermediate symlinks are refused with a `warn`; the cache base itself may be a symlink.

Keep methods small enough to satisfy `phpmd` complexity rules (split helpers if needed, e.g. `relativeSegmentsAreSafe()` and `resolvesInsideBase()`).

## Files to Change
- `source/source/lib/content/CacheDirCleaner.php` — new private guard method(s) used by `deleteDir()`, `warn` logging of refusals, docblock updates.
