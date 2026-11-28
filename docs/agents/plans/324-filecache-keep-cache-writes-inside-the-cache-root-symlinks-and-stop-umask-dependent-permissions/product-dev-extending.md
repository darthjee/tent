# Product-dev-extending Plan: FileCache: keep cache writes inside the cache root (symlinks) and stop umask-dependent permissions

Main plan: [plan.md](plan.md)

## Shared contracts

Rely on the "Behavior to document" and "Write-skip log line" contracts in [plan.md](plan.md#shared-contracts).

## Implementation Steps

### Step 1 — Update the trust-boundary note

In `docs/guides/tent/cache-configuration.md`, in the path-safety subsection added by #322, replace the "Trust boundary" blockquote (currently "Symbolic links inside it are not defended against when cache entries are read or written."). State that:

- cache writes never follow a symbolic link out of the cache location: the store is skipped and the `cache store skipped — … reason: resolves outside cache location` `warn` is logged, the response itself is unaffected;
- a symbolic link standing in place of a cache file is replaced by a regular file;
- the cache location itself may be a symbolic link;
- tent creates cache directories `0755` and files `0644`, whatever the process umask;
- cache **reads** are not checked for symbolic links (to keep cache hits free of extra filesystem calls), so the cache directory must still be writable only by tent.

Keep it short and consistent with the surrounding wording; do not describe internals (helper names).

## Files to Change

- `docs/guides/tent/cache-configuration.md` — rewrite the trust-boundary note.

## Notes

- Verify the wording against the merged `tent` implementation (log message text, modes) before finishing.
