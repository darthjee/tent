# Sentinel expiry and hardened read

Teach `triggerRefresh()` to tell a live sentinel from an abandoned one, following the issue's Solution section.

**Production code** in `CacheStalenessMiddleware`:

- Constants:
  - `private const DEFAULT_SENTINEL_TTL_SECONDS = 60;` with a docblock saying it is twice the refresh client's fixed 30s cURL timeout.
  - `private const SENTINEL_READ_LIMIT = 32;`
- Property `private int $sentinelTtlSeconds;`. Constructor: append `?int $sentinelTtlSeconds = null` **after** `?array $evictOn = null`, assigning `$sentinelTtlSeconds ?? self::DEFAULT_SENTINEL_TTL_SECONDS`. Update the constructor `@param` block: the 60s default and its reason, and that an explicit `<= 0` means never expires.
- `triggerRefresh(ProcessingRequest $request, CacheHit $hit)` stays a thin orchestrator. It replaces the bare `file_exists()` check with `if ($this->isRefreshInFlight($request, $sentinel)) { return; }`, then calls `writeSentinel()` and `scheduleRefresh()` as today.
- `isRefreshInFlight(ProcessingRequest $request, string $sentinel): bool`:
  - Returns `false` if the sentinel does not exist.
  - Otherwise computes `$age = $this->sentinelTtlSeconds <= 0 ? null : $this->sentinelAge($sentinel, time());`. `time()` is read once here; with TTL `<= 0` nothing is read.
  - If `isFresh($age)`: log the **unchanged** `debug` line `[stale] - refresh already in progress, skipping — uri: ...` and return `true`.
  - Otherwise log `Logger::warn('[stale] - abandoned refresh sentinel taken over — uri: ' . path . ', age: ' . $age . ', sentinelTtlSeconds: ' . ttl)` and return `false`.
- `isFresh(?int $age): bool`: pure, no I/O. Returns `$this->sentinelTtlSeconds <= 0 || $age === null || $age <= $this->sentinelTtlSeconds`.
- `sentinelAge(string $sentinel, int $now): ?int`:
  - Gets `$stored = storedTimestamp($sentinel)`.
  - If `$stored !== null && $stored - $now <= $this->sentinelTtlSeconds`, returns `$now - $stored`.
  - Otherwise, an absent or invalid timestamp, or one more than one TTL in the future (overflow to `PHP_INT_MAX` included), falls back to `@filemtime()`. Returns `null` when that fails too.
- `storedTimestamp(string $sentinel): ?int`:
  - Returns `null` unless `is_file($sentinel)` (a regular file, following links): never open a FIFO, device, socket or directory.
  - Reads with `@file_get_contents($sentinel, false, null, 0, self::SENTINEL_READ_LIMIT)`.
  - Trims the content and returns `(int)` only when `ctype_digit()` passes (not `is_numeric()`); otherwise `null`.
- Update the class docblock and `triggerRefresh()`'s docblock to describe the expiry and the takeover.

**Tests**, in `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareSentinelTest.php`, same commit as the code:

- Fixture changes:
  - `buildMiddleware(?int $sentinelTtlSeconds = null)` passes it as the named argument `sentinelTtlSeconds:`.
  - Extend `sentinelState()` (or the scheduler closure) to also capture the sentinel's **content** at scheduling time. The synchronous recording scheduler runs the `finally` before `processResponse()` returns, so the takeover overwrite is only observable there.
  - Use `touch($path, $mtime)` to set mtimes.
- Content cases, with margins of at least 30s around the TTL; the exact boundary is only tested through `isFresh()`:
  - content `time() - 3600`, TTL 300 → one scheduled refresh, and the captured content is a current timestamp;
  - content `time() - 100`, TTL 300 → debounces, content untouched;
  - `"garbage"` content with an old mtime → takeover; with a fresh mtime → debounce;
  - fresh content with an old mtime → debounce, and old content with a fresh mtime → takeover (content wins);
  - `time() + 30` → debounce;
  - `time() + 86400` with an old mtime → takeover; `'99999999999999999999999999'` (overflow) with an old mtime → takeover;
  - over 32 bytes whose first 32 are not digits, with an old mtime → takeover;
  - TTL `0` and TTL `-1` with content `time() - 86400` → debounce.
- Special files:
  - **Directory** sentinel with an old mtime → one refresh scheduled, nothing thrown, the directory is still present afterwards.
  - **FIFO**: `posix_mkfifo()` (call `markTestSkipped()` if it is missing). `fopen($fifo, 'r+')`, write `"1000"`, keep the handle open, `touch()` the FIFO to now, and symlink the sentinel to it. Expect a debounce (`$scheduled === []`): a regression that reads the FIFO would see `1000` and take it over. Close the handle in `tearDown()`.
- Reflection, following the existing `writeSentinel` pattern:
  - `isFresh()`: `null` → true; TTL 0 or negative → true for any age; `age == TTL` → true; `TTL + 1` → false; negative age → true.
  - `sentinelAge()` on a missing path → `null`.
- Logging, with `Tests\Support` `CapturingLoggerInstance` (`$lines`):
  - the abandoned case logs exactly one `warn` with the fixed prefix;
  - the fresh case logs the existing `debug` line and no `warn`.
- Existing tests: the four existing tests must pass unchanged (default 60s TTL). Add a comment or assertion to `testLinkToAnExistingOutsideFileDebouncesWithoutTouchingIt` noting that it now debounces through the mtime fallback, because the `'untouched'` content is not numeric.

## Files to Change
- `source/source/lib/middlewares/CacheStalenessMiddleware.php` — constants, property, constructor parameter, `isRefreshInFlight()`, `isFresh()`, `sentinelAge()`, `storedTimestamp()`, docblocks.
- `source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareSentinelTest.php` — fixtures and the new cases above.
