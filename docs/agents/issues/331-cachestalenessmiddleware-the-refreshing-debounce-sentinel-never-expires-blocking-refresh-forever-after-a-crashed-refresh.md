# Issue: CacheStalenessMiddleware: the `.refreshing` debounce sentinel never expires, blocking refresh forever after a crashed refresh

## Background

> **Baseline:** this issue is written against `main` after #311 was merged (PR #332, merge commit `8a2e0b5`, 2026-11-28). In that code the staleness check runs in `processResponse()` through `refreshWhenStale()`, driven by the `CacheHit` marker `FileCacheMiddleware` puts on the `ProcessingRequest`. `triggerRefresh()` receives that `CacheHit`.

`CacheStalenessMiddleware` debounces concurrent background refreshes with a sentinel file placed next to the cache entry's meta file. On a stale cache hit, `processResponse()` → `refreshWhenStale()` → `triggerRefresh()` skips scheduling whenever the sentinel exists, and the guard is a bare existence check — `file_exists()` with no notion of age:

```php
private function triggerRefresh(ProcessingRequest $request, CacheHit $hit): void
{
    $sentinel = $this->sentinelPath($hit->cache());

    if (file_exists($sentinel)) {
        Logger::debug('[stale] - refresh already in progress, skipping — uri: ' . $request->requestPath());
        return;
    }

    $this->writeSentinel($sentinel);

    $this->scheduleRefresh($request, $hit, $sentinel);
}
```

The sentinel is written with the current Unix time as its only content and is removed in a `finally` block once `BackgroundRefresher::run()` returns:

```php
private function writeSentinel(string $sentinel): void
{
    FileUtils::writeAtomically($sentinel, (string) time());
}

private static function removeSentinel(string $sentinel): void
{
    if (file_exists($sentinel)) {
        @unlink($sentinel);
    }
}
```

On `main` before #311 the defect was latent, because the request-middleware chain stopped at `FileCacheMiddleware` on a cache hit and `triggerRefresh()` was never reached. With #311 merged, every stale cache hit reaches `triggerRefresh()`, so the defect is live: the first crashed refresh freezes its entry.

### Current behaviour (reference)

| Location | Behaviour today |
| --- | --- |
| [CacheStalenessMiddleware.php - `triggerRefresh()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Called from `refreshWhenStale()` (in turn from `processResponse()`) with the request and its `CacheHit`; sentinel path derived from `$hit->cache()`. Debounces on `file_exists($sentinel)` alone. No age, no timestamp read, no recovery path. |
| [CacheStalenessMiddleware.php - `writeSentinel()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Writes `(string) time()` via `FileUtils::writeAtomically()` (temp file, `chmod 0644`, `rename()` over the target); the boolean result is ignored, and the stored value is never consumed by any reader. |
| [CacheStalenessMiddleware.php - `removeSentinel()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Called only from the `finally` of `scheduleRefresh()`'s task; a process that never reaches `finally` leaks the sentinel. |
| [CacheStalenessMiddleware.php - `sentinelPath()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | `$cache->metaFilePath() . self::REFRESH_SENTINEL_SUFFIX`, i.e. `<meta>.refreshing`. |
| [CacheStalenessMiddleware.php - `build()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Reads `maxAgeSeconds` / `max_age_seconds` cast to `int`, default `0`, plus `evictOn` / `evict_on`; calls `new self($location, $maxAgeSeconds, $host, evictOn: $evictOn)`. Logs a warning for the deprecated header-filter attributes (`mode`, `excluded_headers`, …), which are otherwise ignored since #311. No sentinel-related option exists. |
| [CacheStalenessMiddleware.php - `defaultScheduler()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | `fastcgi_finish_request()` + `register_shutdown_function()` under PHP-FPM, synchronous otherwise; the synchronous branch is the shipped `php:8.4-apache` path, so a slow upstream blocks the worker and is exactly the case that ends with `max_execution_time`. |
| [FileCache.php - `timestamp()`](https://github.com/darthjee/tent/blob/main/source/source/lib/content/FileCache.php) | Reads `meta['timestamp']`, returning `null` when absent or when the entry is inert. Establishes that cache age is already derived from a stored integer, not from mtime. |
| [FileCache.php - `metaFilePath()`](https://github.com/darthjee/tent/blob/main/source/source/lib/content/FileCache.php) | Public accessor documented as existing so "staleness/debounce mechanisms" can derive sentinel paths from it. |

## Problem

Because the sentinel carries a timestamp that nobody reads, and because the only writer of the removal path is the process that created it, the sentinel has no expiry. If the refresh process dies before its `finally` block runs — fatal error, killed PHP-FPM worker, `max_execution_time`, container restart, OOM kill — the sentinel stays on disk forever. Every subsequent request for that cache entry then takes the `file_exists()` branch, logs `refresh already in progress, skipping`, and returns the stale response unchanged. That entry is served stale indefinitely, with a log line that actively misreports the situation: no refresh is in progress.

The failure is self-perpetuating, not transient: nothing in the middleware will ever look at that sentinel again with an expiry in mind, so only a manual `rm` or a cache wipe recovers the entry. The blast radius is per cache entry — each poisoned sentinel permanently freezes exactly one URL, method and query combination — which makes it easy to miss in production: the proxy keeps answering, most entries keep refreshing, and only some paths silently age out.

## Expected Behavior

- A sentinel whose stored timestamp is older than `sentinelTtlSeconds` does not block a refresh: a new refresh is scheduled, the sentinel is overwritten with a fresh timestamp, and a `warn` is logged.
- A sentinel whose stored timestamp is within `sentinelTtlSeconds` still debounces: no refresh scheduled, no write, the existing `debug` line is emitted.
- A sentinel with non-numeric content is aged by `filemtime()`, and the same fresh/abandoned rules apply.
- A sentinel that cannot be dated at all is treated as fresh and keeps debouncing.
- `sentinelTtlSeconds <= 0` disables expiry entirely: an arbitrarily old sentinel still debounces.
- `build()` accepts `sentinel_ttl_seconds`, casts it to `int` when present, and leaves it to the constructor's 60s default when absent. `maxAgeSeconds` has no influence on the sentinel TTL.
- Sentinels written by the current release remain valid input for the new code — no format migration, no version marker.
- The middleware's public constructor is backward compatible: existing positional arguments keep their meaning.
- The stale response served to the client is byte-identical to what is served today; the change affects only whether a refresh is scheduled.

## Solution

Give the sentinel the expiry it was always missing: read the timestamp it already stores, compare it against a configurable TTL, and treat an over-age sentinel as abandoned.

```php
private const REFRESH_SENTINEL_SUFFIX = '.refreshing';

/**
 * Default sentinel TTL: twice the refresh client's fixed 30s total curl timeout.
 */
private const DEFAULT_SENTINEL_TTL_SECONDS = 60;

private int $sentinelTtlSeconds;

public function __construct(
    FolderLocation $location,
    int $maxAgeSeconds,
    string $host,
    ?HttpClientInterface $httpClient = null,
    ?callable $scheduler = null,
    ?array $excludedHeaders = null,
    ?array $additionalExcludedHeaders = null,
    ?array $allowedHeaders = null,
    string $mode = 'deny',
    ?array $evictOn = null,
    ?int $sentinelTtlSeconds = null
) {
    // ...
    $this->sentinelTtlSeconds = $sentinelTtlSeconds ?? self::DEFAULT_SENTINEL_TTL_SECONDS;
}

private function triggerRefresh(ProcessingRequest $request, CacheHit $hit): void
{
    $sentinel = $this->sentinelPath($hit->cache());

    if ($this->isRefreshInFlight($request, $sentinel)) {
        return;
    }

    $this->writeSentinel($sentinel);

    $this->scheduleRefresh($request, $hit, $sentinel);
}

private function isRefreshInFlight(ProcessingRequest $request, string $sentinel): bool
{
    if (!file_exists($sentinel)) {
        return false;
    }

    // TTL <= 0 never expires: skip the read entirely.
    $age = $this->sentinelTtlSeconds <= 0 ? null : $this->sentinelAge($sentinel, time());

    if ($this->isFresh($age)) {
        Logger::debug('[stale] - refresh already in progress, skipping — uri: ' . $request->requestPath());
        return true;
    }

    Logger::warn(
        '[stale] - abandoned refresh sentinel taken over — uri: ' . $request->requestPath() .
        ', age: ' . $age . ', sentinelTtlSeconds: ' . $this->sentinelTtlSeconds
    );

    return false;
}

/**
 * Pure debounce rule: an undatable sentinel (null) and a disabled TTL both count as fresh.
 */
private function isFresh(?int $age): bool
{
    return $this->sentinelTtlSeconds <= 0 || $age === null || $age <= $this->sentinelTtlSeconds;
}

private const SENTINEL_READ_LIMIT = 32;

private function sentinelAge(string $sentinel, int $now): ?int
{
    $stored = $this->storedTimestamp($sentinel);

    // A timestamp more than one TTL in the future is not skew, it is garbage
    // (or an overflowing digit string cast to PHP_INT_MAX): fall back to mtime.
    if ($stored !== null && $stored - $now <= $this->sentinelTtlSeconds) {
        return $now - $stored;
    }

    $mtime = @filemtime($sentinel);

    return $mtime === false ? null : $now - $mtime;
}

private function storedTimestamp(string $sentinel): ?int
{
    // Regular files only (following the link): never read a FIFO, device or directory.
    if (!is_file($sentinel)) {
        return null;
    }

    $content = @file_get_contents($sentinel, false, null, 0, self::SENTINEL_READ_LIMIT);

    if ($content === false) {
        return null;
    }

    $content = trim($content);

    return ctype_digit($content) ? (int) $content : null;
}
```

`build()` gains the matching option. It is cast to `int` when present and passed as `null` when absent, so the constructor applies the 60s default. A bare `(int)` cast over `?? null` would turn "absent" into `0`, which means "never expires", so that cast must not be used:

```php
$sentinelTtl = $attributes['sentinel_ttl_seconds'] ?? $attributes['sentinelTtlSeconds'] ?? null;
$sentinelTtlSeconds = $sentinelTtl === null ? null : (int) $sentinelTtl;

return new self(
    $location,
    $maxAgeSeconds,
    $host,
    evictOn: $evictOn,
    sentinelTtlSeconds: $sentinelTtlSeconds
);
```

### Semantics

1. The sentinel is written exactly as today: `(string) time()`, best-effort, through `FileUtils::writeAtomically()` (whose `false` return stays ignored). The write format does not change, so sentinels written by a previous version are readable by the new code and vice versa.
2. A sentinel is "fresh" while `age <= sentinelTtlSeconds`: it still debounces, unchanged behaviour, `debug` line preserved verbatim.
3. A sentinel is "abandoned" when `age > sentinelTtlSeconds`: it no longer debounces. The middleware logs `warn`, overwrites the sentinel with a fresh `time()`, and schedules the refresh through the normal `scheduleRefresh()` path.
4. The TTL defaults to a fixed **60 seconds**, independent of `maxAgeSeconds`. `sentinelTtlSeconds <= 0`, set explicitly, means the sentinel never expires: today's behaviour, kept as the documented escape hatch. The escape hatch is reached only by explicit configuration, never by default.
5. Age is computed from the sentinel's stored content when that content is a non-empty digit string; `filemtime()` is the fallback when the content is empty, unreadable or non-numeric, when the sentinel is not a regular file (`is_file()` false: FIFO, device, directory, after following any link), or when the stored timestamp lies more than one TTL in the future. The content is read with a hard cap of `SENTINEL_READ_LIMIT` (32) bytes. Content wins over mtime, so a copied or restored sentinel is dated by what it says, not by when it landed on this filesystem.
6. When neither the content nor the mtime can be read, the sentinel is treated as fresh. An undatable sentinel must not silently authorize a refresh takeover; correctness of the debounce is preferred over freshness of the cache in the ambiguous case.
7. Userspace only: the check stays inside `triggerRefresh()`. The `processResponse()` → `refreshWhenStale()` flow from #311, the `CacheHit` marker, the `Middleware::processRequest()` / `processResponse()` signatures, the scheduler abstraction and `BackgroundRefresher` are untouched.

### Interaction with the existing lifecycle

`triggerRefresh()` remains the only entry point that writes the sentinel, so the abandoned-takeover branch inserts itself before the existing `writeSentinel()` call rather than beside it. Consequence: takeover reuses the very same write and scheduling code, and the `finally`-based removal is unchanged. A takeover therefore produces exactly one refresh task, with the sentinel belonging to the newest taker.

### Detection of the abandoned state

The detection is deliberately conservative and cheap: one `file_exists()`, one `is_file()`, one `file_get_contents()` capped at 32 bytes and, at most, one `filemtime()` per stale-hit refresh decision — all on a single small file. The read can neither block (no FIFOs or devices are opened) nor grow with the file's size. No locks, no additional files, no directories to scan. The information needed was already being stored by `writeSentinel()`; this change consumes it for the first time.

### Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Age source | Sentinel content (`time()`), fallback `filemtime()` | `writeSentinel()` already stores the timestamp and no reader consumed it. Consistent with `FileCache::timestamp()`, which dates cache entries from stored metadata rather than mtime. Because `writeAtomically()` renames a fully written temp file into place, this process never leaves a sentinel with partial content. mtime is only the safety net for sentinels emptied or replaced by a foreign writer (an operator's `touch`, a backup tool). |
| TTL source | New dedicated option `sentinel_ttl_seconds`, default fixed at 60s (`DEFAULT_SENTINEL_TTL_SECONDS`) | The TTL has to bound how long a *healthy* refresh can run, which has nothing to do with how stale an entry may get. `CacheStalenessMiddleware` builds its client as `new CurlHttpClient()` with no options, so every refresh is capped by curl's fixed 30s total timeout (`CurlHttpClient.php`). 60s is twice that. Deriving the TTL from `maxAgeSeconds` was rejected because it fails in both directions. With `maxAgeSeconds < 30`, healthy slow refreshes get taken over, which also triggers the #333 race. With `maxAgeSeconds = 0` the TTL would be 0, meaning never expires, so the most aggressive "always refresh" setting would keep this very bug. With a large `maxAgeSeconds` (e.g. 86400), one crash freezes the entry for a day. `max(maxAgeSeconds, 60)` was also rejected: it still has the large-value problem. If the refresh client ever gets configurable timeouts, the default should follow them; until then the constant is documented as tied to the 30s cap. |
| Action on an abandoned sentinel | Overwrite it and schedule the refresh; log `warn` | Removing only and letting the next request schedule would add a request of latency to every recovery, and on a low-traffic entry could delay it by minutes. Warning is right for a state that indicates a previous process died. |
| Takeover atomicity | Atomic per-write (`writeAtomically()`, unchanged); no atomic check-and-claim | Each sentinel write already lands atomically, so a reader never sees partial content. The *check-then-write* sequence in `triggerRefresh()` is still not exclusive: two simultaneous takeovers can both see an abandoned sentinel and both schedule a refresh. That is acceptable: both write the same cache entry, the result is idempotent and the cost is one redundant upstream call. An exclusive claim (`O_EXCL` / `fopen('x')`, `flock()`) would add a failure mode (a lost refresh when the claimer crashes) worse than the duplicate it prevents. |
| Where the check lives | Inside `triggerRefresh()`, before `writeSentinel()` | Single writer of the sentinel, single place to reason about its lifecycle. Keeps `scheduleRefresh()` and the scheduler abstraction free of debounce logic. |
| Undatable sentinel | Treated as fresh | Fail closed on the debounce. A sentinel we cannot interpret may belong to a healthy in-flight refresh; assuming abandonment would defeat the debounce for the whole window. |
| Invalid `sentinel_ttl_seconds` | Cast to `int`; `<= 0` means never expires | Matches the existing style of `build()`, which already casts `maxAgeSeconds`. Configuration errors must not throw at rule-build time and take the whole proxy down for a debounce detail. |
| Sentinel file format | Unchanged: plain `(string) time()` | Zero-migration. A rolling restart with mixed versions keeps working, which matters because the sentinel lives on a Docker volume that outlives the container. |

### Alternatives considered (rejected)

| Alternative | Why not |
| --- | --- |
| `flock()` on the sentinel (non-blocking `LOCK_EX`) | The kernel releases the lock when the process dies, so a stale state can't happen at all. Rejected for three reasons. It needs an `fopen()` handle, and `fopen()` follows symlinks, which reopens the symlink escape that #324 closed for cache writes. Lock semantics are unreliable on some Docker volume backends (NFS, macOS bind mounts). And under `fastcgi_finish_request()` the handle must survive into the shutdown function, which adds lifecycle complexity for a best-effort debounce. |
| Store the PID in the sentinel and probe it with `posix_kill($pid, 0)` | PIDs are per-container, while the cache volume is shared across containers. PIDs get reused. It also requires the `posix` extension. Liveness checks give false answers exactly in the restart scenarios this issue targets. |
| Record refresh state in the cache meta file (`refreshingSince`) | Couples the debounce to `FileCache`'s meta format, and the refresh itself rewrites meta, which mixes two lifecycles in one file. It also contradicts Implementation notes ("no change to `FileCache`"). |
| Age by `filemtime()` only | Simpler, but it throws away the timestamp `writeSentinel()` already stores, and a copied or restored sentinel gets a fresh mtime. Reading one small file is negligible, so content-first with an mtime fallback was kept. |
| TTL = `maxAgeSeconds`, or `max(maxAgeSeconds, 60)` | See the "TTL source" row in Design decisions. |

## Scope

**In scope**

- Expiry of the `.refreshing` sentinel: age read from its content, `filemtime()` fallback, a configurable TTL, and takeover of an abandoned sentinel with a `warn`.
- New `sentinel_ttl_seconds` / `sentinelTtlSeconds` option in `build()` and the constructor (11th parameter, default fixed at 60s, an explicit `<= 0` means never expires).
- Unit tests in the `CacheStalenessMiddleware` suite and the docs updates listed under Implementation notes.

**Out of scope**

- A slow refresh's `finally` deleting the sentinel of the refresh that took it over (conditional, token-checked removal): [#333](https://github.com/darthjee/tent/issues/333).
- The `processResponse()` / `CacheHit` refresh flow itself: delivered by #311 (PR #332), taken here as the baseline.
- An exclusive, atomic claim of the sentinel (`O_EXCL`, `flock()`). Rejected in Design decisions; duplicate refreshes on a simultaneous takeover are accepted.
- Repetition control for the takeover `warn` (see Deferred).
- Changes to the scheduler (`fastcgi_finish_request` vs. synchronous), to `BackgroundRefresher` or to `FileCache`.
- Sweeping or cleaning up sentinels of entries that are never requested again. An abandoned sentinel is only ever recovered lazily, on the next stale hit for that entry.

## Edge cases

- `maxAgeSeconds = 0` and no `sentinel_ttl_seconds` → TTL is the 60s default. Every hit is stale, so a refresh is attempted on each hit, while the debounce holds for 60s and a crashed refresh is recovered after 60s. `maxAgeSeconds` never feeds the TTL.
- `sentinel_ttl_seconds` explicitly `0` or negative → the sentinel never expires (escape hatch). A non-numeric value casts to `0` and therefore also means never expires; the docs must say so.
- Sentinel content is `""` or whitespace → not a digit string → fall back to `filemtime()`.
- Sentinel content is a timestamp slightly in the future, at most one TTL ahead (clock skew between containers sharing the volume) → `age` is negative → fresh → debounce. No warning.
- Sentinel content is a timestamp more than one TTL in the future, or a digit string long enough to overflow `(int)` to `PHP_INT_MAX` → treated as invalid content → aged by `filemtime()`. Without this rule, a bogus far-future value would debounce "forever" and recreate the bug this issue fixes.
- `filemtime()` itself in the future (e.g. `touch -d` with a future date) → negative age → fresh. This is not special-cased: it needs deliberate tampering, and it follows the fail-closed rule of Semantics 6.
- Sentinel content longer than 32 bytes → only the first 32 bytes are read. A long digit string becomes an overflowing or far-future value (→ mtime, above); anything else is non-numeric (→ mtime).
- Sentinel is, or links to, a FIFO, character device (e.g. `/dev/zero`) or socket → `is_file()` is false → never opened → aged by `filemtime()`. Reading it could block the request forever (FIFO) or without bound (`/dev/zero`).
- Sentinel is a directory → `is_file()` is false → aged by the directory's mtime. Once abandoned, `writeAtomically()` cannot rename a file over a directory and returns `false`; the refresh is scheduled anyway, and the `finally` `unlink()` fails too. From then on every stale hit for that entry schedules a refresh and logs the takeover `warn`: the entry loses its debounce but keeps refreshing. This degradation is accepted, the same as the unwritable-directory case, and the `warn` makes it visible. Treating a non-regular sentinel as fresh was rejected because it would freeze the entry again.
- Sentinel exists but is not readable (mode `000`, foreign owner) → `file_get_contents()` fails → `filemtime()` (which needs no read permission) dates it → recovered normally. The overwrite only needs write permission on the directory (`rename()`).
- A refresh that crashes deterministically for one entry (e.g. OOM on a huge upstream body) → one takeover plus one `warn` every TTL (60s by default) for that entry: a bounded retry, not a loop. Log repetition stays under Deferred.
- First stale hits after upgrading to this release → every sentinel leaked by the old version is taken over once, so expect a burst of takeover `warn`s right after deploy. That burst is the recovery happening, not a regression.
- Sentinel is a dangling symlink → `file_exists()` is false, so the path is not even inspected as a sentinel; `writeSentinel()` then renames a regular file over the link itself, without creating the link's target (covered today by `testSentinelReplacesADanglingLinkWithoutCreatingItsTarget`).
- Sentinel is a symlink to an existing outside file → `file_exists()` is true; aged by its content or target mtime; if fresh, still debounces. No write is performed unless it is abandoned; in the abandoned case `writeAtomically()`'s `rename()` replaces the link itself and leaves the outside target untouched (the existing `testWritingTheSentinelReplacesALinkWithoutTouchingItsTarget` guarantee).
- Sentinel directory is not writable → `writeAtomically()` returns `false` (and cleans up any temp file), `writeSentinel()` ignores it, and `scheduleRefresh()` still runs. A failed takeover write degrades to today's per-request scheduling, not to a lost refresh.
- Very long-lived process holding the sentinel across a container restart → its `finally` runs before the restart completes, so the sentinel is removed; if the container is killed hard, the sentinel is expired by this fix.
- Inert cache entry (unsafe or overlong path) → `FileCacheMiddleware` never serves it, so no `CacheHit` is set; and if one were, `$hit->cache()->timestamp()` returns `null` and `refreshWhenStale()` returns before `triggerRefresh()`. Unreachable for sentinels too.

## Backward compatibility

**Mixed versions on the shared cache volume** (rolling deploys, several containers, rollbacks). The sentinel lives on a Docker volume that outlives any single container, so old and new code will read each other's sentinels:

| Writer → reader | Outcome |
| --- | --- |
| Pre-#331 → #331 | Compatible: the old writer stores `(string) time()`, which the new reader parses. Sentinels leaked by old crashes are taken over on the next stale hit after the TTL. |
| #331 → pre-#331 | Compatible: the format is unchanged, and the old reader only checks existence. |
| Old and new containers side by side | An old container's crashed refresh still leaks its sentinel, and a new container recovers it after the TTL. Until then the old container treats it as in flight (today's behaviour). The #333 race can happen across versions with the same impact as within one version. |
| Rollback to pre-#331 | Leftover sentinels are in the same format. The rolled-back code goes back to never expiring them, which is the old bug, not corruption. |
| #333 (future `<digits>:<suffix>` token) → #331 | Must stay compatible: the token fails `ctype_digit()`, so #331 ages it by `filemtime()`, which for an atomically written file is its write time. This is a requirement on #331's parser: invalid content must always fall back to mtime and must never be treated as undatable. |

**Code and configuration**

- Constructor: `CacheStalenessMiddleware` is not `final`, so subclasses may exist. The new parameter is appended last with a `null` default, and all new members are `private`, so a subclass calling `parent::__construct(...)` positionally keeps working.
- Configuration: `sentinel_ttl_seconds` is optional. A config that contains it, deployed to an older version, is silently ignored because `build()` only reads known keys, so downgrades are safe.
- Logs: the existing `refresh already in progress, skipping` `debug` line is unchanged. The new takeover `warn` has a fixed, greppable prefix; only log-based alerting is affected.

**Default behaviour change and versioning**

- The default moves from "a sentinel never expires" to "a sentinel is taken over after 60s". The old behaviour was never documented and is the defect itself, so this is a bug fix, not a breaking change. It is still called out explicitly in the docs: *abandoned refresh sentinels are now taken over after 60s (logged at `warn`); set `sentinel_ttl_seconds: 0` to restore the previous never-expire behaviour.*
- The new option makes this a **minor** version bump (the project is at `1.3.0` in `source/composer.json`).

**Release coupling**

- #331 must ship in the **same release** as #311. Once #311 makes the refresh path reachable, a release without #331 would let a single crashed refresh freeze its entry forever. #311 is already on `main` (`8a2e0b5`) and still unreleased (the project is at `1.3.0`). No version may be tagged or bumped until #331 is merged too.

## Performance and security

**Cost model**

- Fresh cache hits never reach the sentinel. A stale hit with no sentinel pays exactly today's single `file_exists()`.
- The added work (`is_file()`, a read of at most 32 bytes, and at most one `filemtime()`) happens only on a stale hit while a sentinel exists. That is precisely the case that is currently debounced or stuck.
- No `clearstatcache()` is needed. PHP's per-request stat cache is shared by `file_exists()`, `is_file()` and `filemtime()`, which is harmless because each request makes exactly one debounce decision on the path.
- Upstream load: today a stuck entry causes zero refreshes. After this change, each entry whose refresh crashed causes at most one refresh per TTL (60s by default), and only while it keeps receiving stale hits. This added load is the intended recovery, not a regression.

**Threat notes**

- The read path is hardened as described in Edge cases: `is_file()` guard (no FIFO, device, socket or directory is ever opened, symlinks included) and a 32-byte read cap.
- Residual TOCTOU, accepted: the sentinel can be swapped for a FIFO between `is_file()` and `file_get_contents()`, which would block that request. Exploiting it needs write access to the cache volume, which already allows rewriting cache entries outright. Closing the window would need a non-blocking open, which PHP's stream API does not provide for plain files.
- A takeover cannot be triggered from outside. It requires an old sentinel, which exists only after a crashed refresh or tampering with the volume. An external client can at most make refreshes crash (e.g. through a huge upstream body), and the TTL limits that to one retry per entry every 60s, on a refresh path that is already reachable today.
- Logging: the takeover `warn` includes `requestPath()` verbatim, exactly like the middleware's existing `debug` lines and `FileCache`'s `warn`s. `LoggerInstance::log()` does not sanitize messages before `error_log()`. That is a pre-existing, project-wide concern and out of scope here. The only difference is that this line is emitted at `warn`, so it is more likely to pass the configured threshold.
- No new file is created besides the existing sentinel, no new permission is required, and the write path (`FileUtils::writeAtomically()`, 0644, rename over links) is unchanged.

## Deferred

- A slow refresher that outlives the TTL: it is taken over, and when it finally returns it removes the sentinel unconditionally, which can delete the newer taker's sentinel and allow a third refresh. Not required to fix this defect and not decided here; if it is to be addressed, the natural follow-up is to make the `finally` removal conditional on the sentinel still containing this execution's timestamp — which requires the taker's token to be carried into `scheduleRefresh()`. Spun off as [#333](https://github.com/darthjee/tent/issues/333). #331 must not pre-empt it, but it must not close the door either: keep `writeSentinel()`'s format and the `sentinelAge()` parser simple enough that #333 can extend them, for example to a `<digits>[:<suffix>]` token.
- Repetition control for the `warn`: one line per takeover is the starting point. If a persistently failing refresh turns the volume's log into noise, a per-request flag on `ProcessingRequest` (the pattern already used by `overlongPathReported()`) is the obvious lever. Left open until the log volume is observed in practice.

## Implementation notes

- Files touched: `source/source/lib/middlewares/CacheStalenessMiddleware.php` only for production code. No new class, no change to `FileCache`.
- `source/source/loader.php` needs no entry: there is no new file, and the manual loader only lists files.
- Constructor: `?int $sentinelTtlSeconds = null` is appended as the **11th** positional parameter, after the existing `?array $evictOn = null` (added for #306). It must not be inserted before `$evictOn`, which would break existing positional callers. `build()` already uses named arguments since #311 (`evictOn: $evictOn`), so it just adds `sentinelTtlSeconds: $sentinelTtlSeconds`. Do not resurrect the deprecated header-filter options while touching `build()`; they stay ignored and warned about.
- The sentinel write stays `FileUtils::writeAtomically()`; do not reintroduce `@file_put_contents()` for the takeover overwrite. The takeover reuses `writeSentinel()` unchanged.
- New private members: `$sentinelTtlSeconds`, `isRefreshInFlight()`, `sentinelAge()`, `storedTimestamp()`, `isFresh()`. `isFresh()` is a pure function of `?int $age` and the TTL, with no I/O; it is the single place the fresh/abandoned rule lives, so it can be tested exhaustively. Keep `triggerRefresh()` a thin orchestrator so the debounce rules stay readable in one place.
- The `warn` message must follow the project's `[status] - reason — key: value` log convention used elsewhere in the middleware, and must be greppable: keep the fixed prefix `[stale] - abandoned refresh sentinel taken over`.
- `build()` must accept both `sentinel_ttl_seconds` (snake_case, matching the other rule attributes) and `sentinelTtlSeconds`, mirroring the `maxAgeSeconds` / `max_age_seconds` pair. Document the new option in the `build()` docblock and in the constructor's `@param` block, including the fixed 60s default (and why: twice the refresh client's 30s curl timeout) and the meaning of an explicit `<= 0`.
- PHP 8.4 / PSR-12: `ctype_digit()` on the trimmed content is the intended numeric test — `is_numeric()` would also accept `1.5` and `1e9`, which are not valid timestamps here.
- `time()` is read once per decision, in `isRefreshInFlight()`, and passed to `sentinelAge()` as `$now`, so the age arithmetic is internally consistent.
- New private constant `SENTINEL_READ_LIMIT = 32` and helper `storedTimestamp()`. The `is_file()` guard and the read cap are a security requirement, not an optimisation (see the Edge cases section), and must not be removed during refactoring.
- Because the shipped image is `php:8.4-apache`, the default scheduler runs refreshes synchronously. The sentinel therefore protects against overlapping refreshes across workers, not within one request; the TTL is the only thing that recovers a killed worker.
- Docs must include the behaviour-change callout from Backward compatibility (60s takeover, `sentinel_ttl_seconds: 0` restores the old behaviour).
- Docs impact: `docs/guides/tent/middlewares.md` §`CacheStalenessMiddleware` and `docs/guides/tent/cache-configuration.md` should list `sentinel_ttl_seconds`, state the fixed 60s default (independent of `max_age_seconds`) and the explicit `<= 0` escape hatch, and explain that an abandoned sentinel is taken over with a `warn`. The class docblock should mirror the guide.

## Testing

Framework: PHPUnit, in `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/`. The suite already contains `CacheStalenessMiddlewareBuildTest.php`, `CacheStalenessMiddlewareOverlongPathTest.php`, `CacheStalenessMiddlewareProcessResponseTest.php` (renamed from `...ProcessRequestTest.php` by #311) and `CacheStalenessMiddlewareSentinelTest.php`. All of them drive the middleware through `processResponse($request->response())` on a request carrying a `CacheHit`.

Extend [CacheStalenessMiddlewareSentinelTest.php](https://github.com/darthjee/tent/blob/main/source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareSentinelTest.php), reusing its existing fixtures (`$this->sentinel`, `buildMiddleware()`, `buildStaleRequest()`, which sets both the response and a `CacheHit`, the recording `$scheduled` scheduler, `Logger::setInstance(new NullLoggerInstance())` in `setUp()`).

The recording scheduler runs the task synchronously, so the `finally` removes the sentinel before `processResponse()` returns. "The sentinel was overwritten with a fresh timestamp" therefore cannot be checked on disk afterwards. It must be captured at scheduling time: extend `sentinelState()` (or the scheduler closure) to also record the sentinel's content, and assert on the `$scheduled` entry. `buildMiddleware()` needs an optional TTL argument, passed as a named `sentinelTtlSeconds:` argument.

Cases:

- A sentinel whose content is `time() - 3600` with `sentinelTtlSeconds = 300` does not block: `$scheduled` has one entry, the sentinel content is rewritten to a current timestamp, and the refresh runs.
- A sentinel whose content is `time() - 100` with `sentinelTtlSeconds = 300` still debounces: `$scheduled` is empty and the sentinel content is untouched.
- A sentinel with non-numeric content (`"garbage"`) and an mtime older than the TTL is treated as abandoned: one refresh scheduled.
- A sentinel with non-numeric content and a modern mtime debounces: `$scheduled` is empty.
- A sentinel whose content is `time() + 30` (skew within the TTL) debounces.
- A sentinel whose content is `time() + 86400` (more than one TTL ahead) and whose mtime is old is taken over. So is one whose content is `'99999999999999999999999999'` (overflow).
- A sentinel that is a symlink to a FIFO must never be read. The test image (official `php` base) has `posix` but not `pcntl`, so `pcntl_alarm()` cannot act as a timeout. Use a preloaded FIFO instead:
  1. Create it with `posix_mkfifo()`, and `markTestSkipped()` when that function is unavailable.
  2. Open it with `fopen($fifo, 'r+')`. On Linux this does not block and holds both ends open. Write `"1000"` (an ancient timestamp), keep the handle open, and `touch()` the FIFO to a current mtime.
  3. Symlink the sentinel to it.
  - **Correct code:** `is_file()` is false, so the FIFO is aged by mtime, which is fresh, and the refresh debounces (`$scheduled` is empty).
  - **Regression** (the content is read): `1000` is ancient, so the sentinel is taken over and `$scheduled` has one entry.
  - The two outcomes are told apart deterministically, and the test never hangs. Close the handle in `tearDown()`.
- A sentinel that is a directory with an old mtime: one refresh is scheduled, nothing throws, and the directory is still there afterwards.
- A sentinel larger than 32 bytes whose first 32 bytes are not digits is aged by mtime.
- A sentinel whose numeric content is fresh but whose mtime is old debounces — proving content wins over mtime.
- A sentinel whose numeric content is old but whose mtime is modern is treated as abandoned — the symmetric proof.
- `sentinelTtlSeconds = 0` with content `time() - 86400` still debounces: `$scheduled` is empty. Same for a negative value.
- The undatable case (the sentinel exists but neither its content nor its mtime can be read) only arises from a race, such as removal between `file_exists()` and the read, so it is not reproduced end to end. It is covered at the unit level instead:
  - `isFresh()` via `ReflectionMethod`, following the suite's existing `writeSentinel` reflection pattern: `null` → fresh; TTL `0` and negative → fresh for any age; age `== TTL` → fresh; age `TTL + 1` → abandoned; negative age (skew) → fresh. The exact boundary is tested here only: end to end it would be flaky across a `time()` second rollover, so the end-to-end cases keep wide margins (±30s around the TTL or more).
  - `sentinelAge()` via reflection on a path that does not exist → `null`.
- The abandoned case emits the `warn` line: install the existing `Tests\Support` `CapturingLoggerInstance` (`source/tests/support/log/CapturingLoggerInstance.php`, public `$lines`) for this test and assert that the fixed prefix `[stale] - abandoned refresh sentinel taken over` appears exactly once, at `warn`.
- The fresh case still emits the existing `debug` line `refresh already in progress, skipping` and no `warn`.

Add to [CacheStalenessMiddlewareBuildTest.php](https://github.com/darthjee/tent/blob/main/source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php):

- `sentinel_ttl_seconds` is honoured when configured.
- When absent, the TTL is 60s whatever `maxAgeSeconds` is. Assert through behaviour, not by reflecting into the private property: with `maxAgeSeconds = 300`, a sentinel aged 90s triggers a takeover and one aged 30s debounces.
- A non-numeric `sentinel_ttl_seconds` (`"abc"`) casts to `0`, which means never expires, and does not throw.
- `maxAgeSeconds = 0` with no sentinel option still gets the 60s TTL: a sentinel aged 90s is taken over. This is the regression guard for the "always refresh" setting.

Existing tests must keep passing unchanged: `testSentinelIsARegularFileNotWritableByOthers`, `testSentinelReplacesADanglingLinkWithoutCreatingItsTarget`, `testLinkToAnExistingOutsideFileDebouncesWithoutTouchingIt` and `testWritingTheSentinelReplacesALinkWithoutTouchingItsTarget` all construct the middleware with `maxAgeSeconds = 300` and no TTL, so the TTL is the 60s default. Their sentinels must stay fresh under the new rules: three of them write it themselves (current `time()`, or no pre-existing file), and `testLinkToAnExistingOutsideFileDebouncesWithoutTouchingIt` is the case to watch. Its sentinel is a symlink to an outside file whose content is `'untouched'`. That content is non-numeric, so the new code ages it by the target's `filemtime()`, which was just written and is therefore fresh, and it keeps debouncing. The test passes unchanged, but now through the mtime fallback; note this in the test or add an explicit assertion so a future fixture change does not silently flip it to a takeover.

Handler-level regression, in [ProxyRequestHandlerStalenessTest.php](https://github.com/darthjee/tent/blob/main/source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerStalenessTest.php), using the `StaleCacheScenario` trait (`source/tests/support/handlers/StaleCacheScenario.php`) from #311. #311 slipped through precisely because nothing drove the middleware through a `RequestHandler`, so this path is covered too:

- `storeEntry(age: 1000)` plus a leaked sentinel next to `entry()->metaFilePath()` with content `time() - 3600` → one request through the handler → the stubbed scheduler has run once (`$this->scheduled === 1`), and the client gets the cached body.
- Same setup with `stalenessConfig(['sentinel_ttl_seconds' => 0])` → `$this->scheduled === 0`: the escape hatch keeps today's behaviour through the full chain.

`DefaultProxyRequestHandlerStalenessTest.php` needs no new cases. It shares the same middleware code path, and the two cases above prove the wiring.

Run with `docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/lib/middlewares/CacheStalenessMiddleware tests/unit/lib/request_handlers/ProxyRequestHandler`, then the full `composer tests` and `composer lint`.

## Context

- Issue: [#331](https://github.com/darthjee/tent/issues/331) — opened 2026-11-28 by `darthjee`, labels `bug`, `shipit`.
- Spun off from [#311](https://github.com/darthjee/tent/issues/311) — "CacheStalenessMiddleware never runs on a cache hit: the request-middleware chain stops at FileCacheMiddleware", which made the refresh path, and this defect, unreachable. #311 was fixed by PR #332, merged on 2026-11-28 (`8a2e0b5`); this issue takes it as its baseline. Both must ship in the same release (see Backward compatibility).
- Related: #306 (refresh-time `evictOn`), #310 (proxy-internal cache-skip flag), #333 (conditional sentinel removal after a takeover, spun off from this issue).
- Affected module: `Tent\Middlewares\CacheStalenessMiddleware`, its `try/finally` lifecycle around `BackgroundRefresher`, and the `build()` option surface.
- Reference doc: `docs/guides/tent/middlewares.md`, §`CacheStalenessMiddleware`; `docs/creating-middlewares.md` describes the short-circuit and sentinel conventions.
- Commit hygiene per `docs/agents/contributing.md`: test and implementation in the same commit; documentation updates in a separate commit.

## Benefits

- A single crashed refresh (fatal error, killed worker, `max_execution_time`, container restart, OOM) no longer freezes its cache entry forever. Recovery is automatic after at most 60s, on the next stale hit.
- The misleading `refresh already in progress` log line stops lying about crashed refreshes: a takeover is reported at `warn` with a greppable prefix.
- Closes the regression that #311 made reachable, before #311 is ever released.
- The sentinel format, write path and public API stay unchanged, so mixed-version deploys on the shared volume keep working.
- Reading the sentinel is hardened from the start (`is_file()` guard, 32-byte cap), so the new read path adds no blocking or memory-exhaustion vector.
