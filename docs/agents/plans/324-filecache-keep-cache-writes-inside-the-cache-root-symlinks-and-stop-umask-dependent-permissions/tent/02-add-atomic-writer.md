# Add the atomic writer

Add `FileUtils::writeAtomically(string $path, string $content): bool` (see the contract in `plan.md`): write `$content` to `$path . '.tmp-' . bin2hex(random_bytes(8))` in the same directory, `chmod($tmp, 0644)`, then `rename($tmp, $path)`. On any failure, remove the temp file (silently, `@unlink`) and return `false`; never throw. `rename()` replaces a symlink standing at `$path` instead of following it — document that in the docblock.

Temp files never match `<hash>.body.dat` / `<hash>.meta.json` / `.refreshing`, so they are never read as entries; a leftover from a crashed process is removed with its directory on the next invalidation.

## Files to Change

- `source/source/lib/utils/FileUtils.php` — add `writeAtomically()`.
- `source/tests/unit/lib/utils/FileUtils/FileUtilsWriteAtomicallyTest.php` — new specs: writes content; overwrites an existing file; replaces a symlink at `$path` (the link's target is untouched, `$path` becomes a regular file); file mode is `0644` under `umask(0)`; no temp file left after success; returns `false` and leaves no temp file when the directory does not exist / is not writable.
