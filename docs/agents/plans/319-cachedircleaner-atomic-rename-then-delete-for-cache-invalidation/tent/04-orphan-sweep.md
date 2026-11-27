# Bounded orphan sweep

After its own deletion, whether by rename or fallback, and including no-op outcomes such as "source already gone", each `CacheDirCleaner` call sweeps **at most one** orphan from the trash.

- Constants: `ORPHAN_GRACE_SECONDS = 600` and `MAX_ORPHANS_PER_CALL = 1`.
- Skip the sweep entirely unless `$this->trash->isUsable()` (it must be a real directory, not a link). Never create the trash just to sweep it.
- List entries with a single `scandir()` (or a `DirectoryIterator`) on the trash path, ignoring `.`/`..`. Skip names for which `entryTimestamp()` returns `null`, since they're foreign and we don't touch what we didn't create. Also skip entries with `ts > now() - ORPHAN_GRACE_SECONDS`, which belong to a possibly still-running cleaner.
- **Claim by rename**: `renameDir(<trash>/<old>, <trash>/<newEntryName(now())>)`.
  - If the claim fails and the old entry is gone, another sweeper won: try the next candidate with no log, still bounded by `MAX_ORPHANS_PER_CALL` successful claims.
  - Any other claim failure is logged at `warn` and the sweep stops for this call.
- After a successful claim, log `cache trash orphan swept — entry: <old name>` at `debug`. Then:
  - If the claimed entry is a link, use `removeFile()`, which unlinks it without following.
  - Otherwise use `removeDirRecursive()`, the #316 walk, which doesn't follow nested links.
- Never remove the `.tent-trash` folder itself.

Tests (driven through the `now()` seam, with entries planted with crafted names):
- An entry older than the grace period is claimed and deleted, with the debug line.
- A younger entry is left alone.
- With two old orphans, exactly one is deleted per call, and the second is deleted on the next call.
- **Lost claim**: `renameDir` removes the old entry and returns `false`. Expect no log and no error.
- A foreign-named entry is left alone.
- A top-level link entry pointing outside the cache is unlinked, and its target is untouched.
- When the trash is a symlink, the sweep doesn't run and the link target is untouched.
- The sweep runs even when the call's own target was already gone.

## Files to Change
- `source/source/lib/content/CacheDirCleaner.php` — sweep logic, constants, docblock section on orphans.
- `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerOrphanSweepTest.php` — new.
