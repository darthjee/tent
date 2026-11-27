# Issue: CacheDirCleaner: atomic rename-then-delete for cache invalidation

## Description

#316 makes `CacheDirCleaner` tolerant of files and folders vanishing during its recursive delete: a new `Tent\Utils\SafeRemover` treats *already gone* as success, and a walk interrupted by a vanished subdirectory is restarted from the root (bounded to 3 attempts). The walk still happens **in place**, on the live cache directory, while other writers (response-side invalidation, probabilistic GC, `FileCache::remove()`, `CacheCleanupMiddleware` — see darthjee/citadel#41) may be touching the same tree.

## Problem

In-place deletion tolerates contention but cannot eliminate it:

- Two cleaners targeting the same directory both walk it, duplicating work and racing on every entry.
- A cache writer can re-create content inside the tree while it is being deleted, so the root `rmdir` can still fail with "directory not empty" and log a `warn`.
- Invalidation is not atomic for readers: during the walk a reader can still hit partially deleted entries.

## Expected Behavior

- Invalidating a cache directory makes it disappear atomically for readers: a single `rename()` into `<base>/.tent-trash/`, after which the entry is deleted privately.
- Concurrent cleaners targeting the same directory do no duplicated walk: exactly one wins the rename, the others treat the target as already gone, silently.
- Writers re-creating content at the original path never land inside the tree being deleted, so the private delete never fails with "directory not empty" because of them.
- Requests whose normalized resolved cache path falls under `<base>/.tent-trash` are proxied but never cached, and cleanup targets resolving there are no-ops.
- Leftovers from interrupted deletions are swept incrementally (at most one per cleaner call, after a 10-minute grace period).
- When the rename cannot be performed, the cleaner logs a `warn` and falls back to today's in-place deletion — invalidation always happens, nothing is ever thrown.

## Solution

Before walking, `CacheDirCleaner::deleteDir()` atomically `rename()`s the resolved root (e.g. `<base>/users/GET`) into a single, dedicated trash folder **inside the cache root**, e.g. `<base>/.tent-trash/<unix-ts>.<random>`, then recursively deletes that private tree using the #316 machinery (`SafeRemover` + bounded restart as a safety net).

- Only one concurrent cleaner wins the `rename()`; losers see the source path gone and treat it as already deleted.
- New writes re-create a fresh directory at the original path, so nothing lands in the tree being deleted.
- Readers see the invalidation atomically.
- Being under `<base>`, the trash folder is always on the same filesystem as every cache directory — including when `<base>` is itself a mount point (e.g. a Docker volume) — so the `rename()` never crosses devices.
- All leftovers live in one place, making orphan handling cheap.
- The trash folder's name becomes a single reserved first path segment that must never be resolved as a cache path (see [Naming and visibility](#naming-and-visibility)).

### Naming and visibility

`FileCache` stores entries at `<base>/<request path>/<METHOD>/<hash>.{body.dat,meta.json}`, so after a rename the hash files sit directly in `<base>/.tent-trash/<entry>/`. Unguarded, a `GET /.tent-trash/<entry>` would make `FileCache` create `<base>/.tent-trash/<entry>/GET/` inside the tree being deleted — the very race this issue removes. Decisions:

- **Name**: `.tent-trash`, defined once (e.g. a constant / `trashPath()` on `FolderLocation` or a small helper) and shared by the cleaner and both guards. The dot prefix plus product name makes a collision with a real API path very unlikely.
- **Reserved rule**: a path is reserved when its **resolved cache path, after lexical normalization** (collapsing `//` and `.`, resolving `..`), equals or lies under `<base>/.tent-trash`. Checking the raw first segment alone is not enough: segments are only filtered for emptiness, so `/./.tent-trash/x` resolves to `<base>/./.tent-trash/x/GET` — inside the trash — while its first segment is `.`. Deeper occurrences (e.g. `/users/.tent-trash`) never resolve into the trash folder and are not affected.
- **Case-insensitive** comparison, applied to the normalized resolved path, so case-insensitive filesystems (macOS dev, some Docker Desktop mounts) cannot reach the trash folder through `/.TENT-TRASH/...`.
- **Guard in `FileCache`** — the single place building read/write paths for `FileCacheMiddleware` and `BackgroundRefresher`: for a reserved path it never reports a hit, `store()` writes nothing (no `mkdir`), and `remove()` is a no-op.
- **Guard in `CacheDirCleaner::deleteDir()`**: a collection/entity/custom target resolving to the trash folder or anything beneath it is a no-op (otherwise invalidating `/.tent-trash/x` would walk the trash in place).
- **Client-visible behaviour**: a request on a reserved path is proxied normally and simply never cached — it is **not** rejected with a 4xx, so the proxy does not alter upstream semantics because of its storage layout.

### Orphans

Orphans are expected more often than "a crash" suggests: a PHP worker hitting `max_execution_time` or an FPM worker being recycled mid-delete leaves a half-deleted entry in `.tent-trash`. Accepting them would be a slow disk leak in exactly the deployments with big caches, so they are swept — without re-introducing the contention the rename removes (a naive "delete everything in the trash" would also walk entries other cleaners are actively deleting). Decisions:

- **Timestamped entries**: `<base>/.tent-trash/<unix-ts>.<random>`, the timestamp being the time of the rename into the trash (directory mtime is unusable — it changes as children are deleted).
- **Where**: the sweep runs inside every `CacheDirCleaner` call, after its own deletion. With an empty trash it costs a single `readdir`; no separate trigger, cron or CLI.
- **Grace period**: only entries older than a fixed threshold (10 minutes, comfortably above any sane `max_execution_time`) are orphans; younger ones belong to a possibly still-running cleaner and are left alone.
- **Claim by rename**: before deleting an orphan, the sweeper renames it to a fresh `<now>.<random>` — only one concurrent sweeper wins, losers see it gone and move on, and the new timestamp protects it from other sweepers while it is being deleted.
- **Bounded work**: at most **one** orphan is claimed per cleaner call, so a backlog cannot add unbounded latency to a single request; it drains incrementally as invalidations happen.
- **Constants, not configuration**: grace period and per-call bound are class constants; making them configurable is left for later if ever needed.
- **Accepted trade-off**: if invalidations stop entirely, orphans wait for the next one — acceptable, since orphans are only produced by invalidations in the first place.

### Rename failure

A failed `rename()` is first classified (after `clearstatcache`, re-check the source path):

- **Source gone** — another cleaner won the race, or the directory never existed: treated as `AlreadyGone`, success, nothing logged. This is the expected outcome for concurrent cleaners, not a failure.
- **Real failure** — the source still exists but could not be moved. Realistic causes: `.tent-trash` cannot be created (permissions, or a regular file occupies the name), the parent directory is not writable, `EXDEV` (still possible if a volume is mounted at a sub-path such as `<base>/users`), or a target-name collision (practically impossible with a `random_bytes` suffix).

On a real failure, the cleaner logs a `warn` including the reason (from `error_get_last()`) and **falls back to in-place deletion** with the #316 path (`SafeRemover` + bounded restart) — invalidation still happens, just without the atomicity guarantees, i.e. exactly today's behaviour. Aborting instead was rejected: it would leave the stale directory in place and keep serving pre-mutation data after a write, a correctness bug worse than the contention this issue reduces.

- Never throws (same contract as today).
- No retry of the rename: every realistic cause is persistent.
- The `cache cleared` debug line keeps its meaning ("this call removed it") on both the rename and the fallback path.

### Symbolic links

- **Symlinked root — #316 rule unchanged**: when the resolved directory is a link, it is `unlink()`ed without being renamed or followed. A single `unlink` is already atomic, so rename-then-delete adds nothing; renaming the link into the trash would only force the sweep to handle links. The check stays before the rename.
- **`.tent-trash` itself must be a real directory** (`is_dir() && !is_link()`). If it is a link, a regular file or anything else, the cleaner treats it as a [real rename failure](#rename-failure) (`warn` + in-place fallback) and never deletes or "repairs" it — that is for the operator to investigate. Without this guard, a link such as `.tent-trash → /var/www` (misconfiguration or a hostile write to the cache volume) would make cleaners rename cache directories into its target and the sweep recursively delete there, turning invalidation into arbitrary file deletion. The sweep applies the same check and skips sweeping when it fails.
- **Links inside the trash**: a top-level trash entry that is a link is claimed by rename (renaming the link itself) and then `unlink()`ed without following; deeper links are already handled by the #316 walk, which does not follow them.
- **Out of scope — intermediate symlinks** (e.g. `<base>/users` pointing elsewhere): the rename either works within the same filesystem or fails with `EXDEV` and falls back; today's behaviour is kept.

### Edge cases

Against the real `FileCache::store()` flow (`ensureCacheFolderExists()` → `file_put_contents(body)` → `file_put_contents(meta)`):

- **Writer racing the rename**: if the rename lands between `mkdir` and the body write, the write fails (directory gone) and no entry is created — the next request is a miss; if it lands between body and meta, the body goes into the trashed tree (deleted with it) and the meta write fails. Harmless either way. The writer still emits PHP warnings (`mkdir` / `file_put_contents` are not `@`-suppressed) — **pre-existing**, in-place deletion produces the same today; out of scope.
- **Stale writer finishing after the invalidation**: a writer that fetched upstream *before* the mutation and stores *after* the rename re-creates a stale entry at the original path. Pre-existing (in-place deletion has the same window) and not fixable by any deletion strategy — explicitly **not addressed** here.
- **Root vanishes before the rename**: source gone → `AlreadyGone` (see [Rename failure](#rename-failure)).
- **`.tent-trash` missing**: created on demand with a non-recursive `mkdir`; if a concurrent `mkdir` wins, re-check `is_dir` and then apply the real-directory check from [Symbolic links](#symbolic-links). `.tent-trash` itself is **never removed**, even when empty, so the sweep never races another cleaner's `mkdir` + rename.
- **Name collision**: on POSIX, `rename()` onto an existing *empty* directory silently replaces it, so a collision with a just-emptied entry could hijack another cleaner's target. `uniqid()` is time-based and can collide across processes within the same microsecond, so the suffix is `bin2hex(random_bytes(8))` instead; a rename that still fails is a real failure and falls back.
- **Overlapping targets**: collection `/users` → `<base>/users/GET` and entity `/users/1` → `<base>/users/1/GET` are sibling trees, never nested (`cleanPath` follows the same `<path>/GET` shape), so one cleaner's target can never sit inside another's. Nothing to handle.
- **Clock jumps**: backwards → orphans look younger and are swept later; forwards → an in-progress entry may be swept early, causing only contention already tolerated by `SafeRemover`. Accepted.

### Backward compatibility

- **Public API unchanged**: `clean()`, `cleanPath()` and the constructor `(FolderLocation, ?SafeRemover)` keep their signatures and the never-throws contract; `CacheCleanupMiddleware` needs no change.
- **`createIterator()` seam kept, argument changes**: still called once per walk attempt, but `$dir` is now the trash entry (`<base>/.tent-trash/<ts>.<random>`) rather than the original path. `CacheDirCleanerRecursiveRemovalTest`'s `racingCleaner` callbacks that disturb `$this->targetDir` mid-walk, and its root-warning assertions, must be adapted to the `$dir` they receive. The seam is protected and documented as test-only, so this is in-scope test rework, not a compatibility break.
- **`cache cleared — dir: <dir>` debug line**: still logs the **original** resolved path (not the trash path), so existing assertions (`CacheDirCleanerCleanTest`, `CacheDirCleanerCleanPathTest`, `CacheCleanupMiddlewareProcessRequestTest`, `clearedLine()`) keep holding. Its meaning becomes "this call invalidated it": on the rename path it is logged when **this call won the rename** (the moment readers see the invalidation) — later failures deleting the trash tree are still `warn`ed by `SafeRemover`; on the fallback path today's meaning is kept (logged when the in-place removal returned `Removed`).
- **New log lines**: a `warn` on rename fallback, including the reason, and when `.tent-trash` is not a real directory; a `debug` line when the sweep claims an orphan (e.g. `cache trash orphan swept — entry: <name>`).
- **Visible on disk**: a permanent `.tent-trash/` folder appears under the cache root; tooling that lists or backs up the cache volume will see it. Documented, not breaking.
- **Behaviour change for one path**: an upstream path whose first segment is `.tent-trash` is no longer cached, only proxied. Documented.
- **Docs to update**: `docs/agents/architecture.md` (the `CacheDirCleaner` paragraph — rename-then-delete, trash folder, sweep, fallback), `docs/guides/tent/middlewares.md` (cache cleanup section — the `.tent-trash` folder, its reserved first path segment, and its permanence), and `CacheDirCleaner`'s class docblock.

### Testing strategy

Follows the existing convention of `CacheDirCleanerRecursiveRemovalTest`: an anonymous subclass overriding protected, test-only seams to inject races deterministically — no real multi-process concurrency.

New protected seams on `CacheDirCleaner` (documented as test-only, like `createIterator()`):

- `renameDir(string $from, string $to): bool` — wraps `@rename`; lets a spec lose the race, fail with the source still present, or "write" right after the rename.
- `now(): int` — wraps `time()`; lets a spec age trash entries without sleeping.
- Entry names are asserted by pattern (`<ts>.<16 hex chars>`) rather than injected.

Specs:

- **Rename path**: target moved and fully deleted, original path gone, `.tent-trash/` left in place and empty; exactly one `cache cleared — dir: <original>` debug line.
- **Lost race**: `renameDir` deletes the source and returns `false` → `AlreadyGone`, no log, no fallback walk (`createIterator` never called).
- **Concurrent writer**: `renameDir` renames, then re-creates `<base>/users/GET/<file>` → the fresh directory is untouched, the trash entry is deleted, no `warn`.
- **Fallback**: `renameDir` returns `false` with the source present → one `warn` with the reason, in-place deletion completes. `.tent-trash` as a regular file → `warn` + fallback, file untouched.
- **Security**: `.tent-trash` as a symlink to a directory outside the cache → `warn` + fallback; the link target's contents are untouched by both cleaner and sweep.
- **Sweep** (via `now()`): an entry older than the grace period is claimed and deleted (`orphan swept` debug line); a younger one is left alone; with two old orphans only one is swept per call; an entry vanishing before the claiming rename → no log, no error; a top-level link in the trash is unlinked with its target untouched.
- **Reserved-path guards**: `FileCache` on `/.tent-trash/x` and `/.TENT-TRASH/x` → `exists()` false, `store()` creates no directory or file, `remove()` no-op; `/users/.tent-trash` is cached normally; `cleanPath('/.tent-trash/x')` is a no-op and leaves existing trash entries untouched; `FileCacheMiddleware` on a reserved path proxies the request and leaves nothing under `<base>/.tent-trash/` from the write side.
- **Existing specs**: the `CacheDirCleanerRecursiveRemovalTest` race specs are adapted to the `$dir` they receive (see [Backward compatibility](#backward-compatibility)); the symlinked-root specs keep passing unchanged, proving #316's rule still holds.
- **Out of scope**: real multi-process stress tests — slow and flaky, and the seams cover each interleaving deterministically.

### Performance and security

Performance:

- `rename()` is an O(1) metadata operation — no data is copied, disk usage never doubles; total deletion work is the same walk as today.
- The invalidating request's latency is roughly unchanged: +1 `is_dir`/`mkdir` on `.tent-trash`, +1 `rename`, +1 `readdir` for the sweep; the walk still runs synchronously in that request.
- Concurrent cleaners now do **zero** walk work — losers stop at the failed rename — a strict improvement over today, where every cleaner walks the whole tree.
- Sweep: one `readdir` per call, at most one orphan claimed (see [Orphans](#orphans)).
- Hot path: the reserved-path guard in `FileCache` is one normalized, case-insensitive prefix comparison per request.
- Disk: trashed data lives only until its walk finishes; orphans live for at least the grace period, then until the next invalidation. No new unbounded growth.
- **Possible follow-up (not in this issue)**: since readers already see the invalidation once the rename succeeds, the walk could run after the response is sent (`fastcgi_finish_request()`), taking deletion off the client's latency entirely.

Security:

- Arbitrary deletion through a symlinked `.tent-trash`: covered in [Symbolic links](#symbolic-links).
- Reserved-name bypass through `//`, `./` or `..` segments: covered by guarding the normalized resolved path (see [Naming and visibility](#naming-and-visibility)), in both `FileCache` and `CacheDirCleaner`.
- Permissions: `.tent-trash` is created with the same mode `FileCache` uses for cache directories (`0777` & umask) — no new exposure.
- Information disclosure: trash entries are never served, since the guard makes them unreachable as cache.
- DoS: flooding invalidations is cheaper than today (rename + bounded sweep); flooding reserved paths merely bypasses the cache, like any uncacheable request.
- **Pre-existing path traversal (out of scope)**: `deleteDir()`'s `str_starts_with($dir, $base . '/')` check is passed by `<base>/../elsewhere/GET`. Whether such a request path can reach tent depends on web-server normalization; either way it exists today, and rename-then-delete does not make it worse (an escaped directory would be moved to the trash and deleted rather than deleted in place). Tracked separately in #322.

### Alternatives considered

- **Sibling rename** (`<base>/users/GET` → `<base>/users/GET.deleting.<uniqid>`): the private name is reachable by a request (`/users/GET.deleting.<uniqid>` resolves to `<base>/users/GET.deleting.<uniqid>/GET`), so writers could land content in the tree being deleted; orphans are scattered across the whole cache, so any sweep needs a full walk. Rejected.
- **Trash folder next to the root** (`./cache` → `./cache.trash/<uniqid>`): unreachable by any request path, but when `<base>` is a mount point (e.g. a Docker volume) the sibling is on another filesystem and `rename()` fails with `EXDEV`, defeating the atomic path in the main deployment shape. Rejected.
- **In-place deletion + lock** (`flock` per directory): dedups cleaners, but keeping writers out would require `FileCache`'s write path to take the lock too (hot-path cost), and readers would still see partial state. Rejected.
- **Generation-based invalidation** (version segment in cache paths, bumped on invalidation): truly atomic with no hot-path deletion, but a redesign of the cache layout far beyond this issue. Rejected as out of scope.

## Scope

In scope:

- `CacheDirCleaner::deleteDir()` — the single shared path behind both `clean()` (collection/entity targets) and `cleanPath()` (custom targets), whose only caller is `CacheCleanupMiddleware` — gains the rename-then-delete behaviour.
- The #316 recursive-delete machinery (`SafeRemover` + bounded restart) is reused unchanged, applied to the renamed private tree.
- The private-name scheme, orphan policy, rename-failure policy and symlinked-root handling settled below.

Out of scope:

- `FileCache::remove()` (used by `FileCacheMiddleware` and `BackgroundRefresher`): it only removes an entry's two files (body + meta), which is already atomic per file and tolerant of concurrent removal — there is no tree walk to protect.
- Garbage collection / invalidation outside tent (e.g. darthjee/citadel#41), and introducing any new general-purpose GC mechanism here (an orphan sweep, if one is decided below, is the only exception).
- Changes to how cache paths are built (`FileUtils` / `CacheDirResolver`), unless the naming/visibility decision requires a guard there.

Depends on #316.

## Benefits

- Atomic invalidation for readers — no partially deleted entries observed mid-walk.
- No duplicated deletion work or per-entry racing between concurrent cleaners.
- Far fewer spurious root-`rmdir` "directory not empty" warnings.
- Bounded, self-healing cleanup of interrupted deletions, with no extra cron or CLI.
- Never worse than today: every failure mode falls back to the current #316 in-place deletion.
