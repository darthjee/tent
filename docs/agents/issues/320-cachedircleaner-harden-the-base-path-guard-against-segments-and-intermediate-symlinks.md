# Issue: CacheDirCleaner: harden the base-path guard against '..' segments and intermediate symlinks

## Description

`CacheDirCleaner::deleteDir()` refuses to delete anything outside the cache location with a single prefix guard:

```php
$base = rtrim($this->location->basePath(), '/');
if (!str_starts_with($dir, $base . '/')) {
    return;
}
```

`$dir` comes from `CacheDirResolver`, which builds `<base>/<segments>/GET` via `FileUtils::getFullPath()` from the request path (`clean()`) or from a configured `custom` target (`cleanPath()`). The resolver splits on `/` and drops empty segments, but does **not** filter `.` or `..` segments, and the result is never normalised before the guard.

darthjee/tent#316 relies on this guard ("only directories under the configured cache location are ever deleted") and closes the symlinked-*root* case, but keeps the guard itself unchanged.

## Problem

1. **`..` traversal passes the guard.** The check is a string prefix on an un-normalised path:
   - `cleanPath('/../..')` → `./cache/../../GET` — starts with `./cache/`, accepted.
   - `clean('collection', '/../x')` → `./cache/../GET` — accepted.

   The cleaner can therefore recursively delete a directory named `GET` outside the cache base. Impact is bounded (the final component is always `GET`), and Apache usually normalises `..` in URLs before PHP sees them, but `custom` targets come from configuration and the guard does not enforce what it claims.
2. **Intermediate symlinks pass the guard.** A symlink in a *non-final* component (e.g. `cache/<segment>` → `/elsewhere`) passes both the prefix guard and #316's root `is_link()` check, so the walk runs inside the link's target. Exploiting this requires write access inside the cache directory, so it is lower risk, but it is the same "guard checks the string, not the real location" weakness.

## Expected Behavior

- `CacheDirCleaner` never deletes, renames or follows anything whose path contains a `.`/`..`/NUL segment after the cache base, or whose real parent directory lies outside `realpath(<base>)` — before any filesystem mutation.
- Such refusals are logged at `warn` as `cache cleanup refused — dir: <dir>, reason: <reason>`; absent paths and trash-folder paths stay silent, as today.
- Legitimate cleanups keep working unchanged, including when the cache base itself is a symlink, and #316's symlinked-root and absent-root behaviour is preserved.

## Solution

Harden the `CacheDirCleaner::deleteDir()` guard so it checks where the path really points, before any filesystem mutation, in a private method on the cleaner:

1. Existing prefix check (`$dir` must start with `<base>/`).
2. **Segment check** (string-only): refuse any `.`, `..` or NUL-containing segment in the part after the base.
3. **`realpath` check**: refuse unless `realpath(dirname($dir))` equals or is under `realpath($base)` — the final component is never resolved.
4. Existing trash-folder check.
5. #316's `is_link()` root handling.

Suspicious refusals are logged at `warn`; absent paths stay silent. The decisions behind each point are detailed below.

Depends on #316 (builds on its `deleteDir()` check order). `FileCache` write paths are tracked separately in #324.

### Where the check lives

The check lives **only in the cleaner guard** (`CacheDirCleaner::deleteDir()`), immediately before any filesystem mutation. `CacheDirResolver` is left unchanged.

- The "only directories under the configured cache location are ever deleted" invariant is a safety property, so it is enforced at the point of deletion, where it protects every current and future caller regardless of how the path was built.
- This makes the guard actually enforce what its docblock (and #316) already claim.
- Resolver-side rejection was considered and dropped: it would duplicate the rule and change `resolveExact()`'s signature to `?string`, without adding protection the guard doesn't already give. If the resolver is ever touched, it must *reject* `..` rather than normalise it — normalising `/users/../admin` to `/admin` would silently delete a different, legitimate cache directory.

### How to check

The guard rejects, string-only, any `.` or `..` segment in the part of `$dir` **after** the base prefix:

1. Strip the `<base>/` prefix from `$dir` (the existing prefix check stays — a path not starting with `<base>/` is still refused).
2. Split the remainder on `/` and refuse the path if any segment is `.` or `..`.

Only the remainder is inspected because the base itself may legitimately contain such segments (the default `./cache`). This closes problem 1 completely, needs no filesystem access, and works for non-existent paths.

- **Rejected alternative — normalise then prefix-compare** (`FileUtils::normalizePath()` on both sides): `./cache/../../GET` would be refused, but `cache/users/../admin/GET` would normalise to `cache/admin/GET` and pass, silently deleting a different, legitimate cache directory.
- **`realpath` comparison** (`realpath(dirname($dir))` vs `realpath($base)`) only adds value against intermediate symlinks; it is adopted — see *Intermediate symlinks* below.

Check order in `deleteDir()`, all before any filesystem mutation: prefix check → `.`/`..` segment check → `realpath` check → existing trash-folder check → #316's `is_link()` root handling.

The check is a **private method** on `CacheDirCleaner` (e.g. `isSafelyScoped(string $dir): bool`). It is only extracted into a reusable helper (e.g. `FolderLocation::contains()`) if `FileCache`/`File` paths are found to need the same treatment.

### Intermediate symlinks

Problem 2 is fixed in this issue with a `realpath` check in the same guard: the path is refused unless `realpath(dirname($dir))` equals `realpath($base)` or starts with `realpath($base) . '/'`.

Rules:

- **Compare against `realpath($base)`, never the raw base string** — the base itself is often a symlink (e.g. `./cache` pointing at a Docker volume), and legitimate cleanups must keep working there.
- **Never resolve the final component** — only its parent. This preserves #316: a symlinked root still passes the guard and is unlinked by `is_link()`, never followed.
- When either `realpath()` returns `false` (parent or base absent), the call is a silent no-op — the same observable outcome as today, where a missing directory is ignored without logging.
- `cleanPath('/')` (`$dir` = `<base>/GET`) is accepted via the "equals" case.

Walking every intermediate component with `is_link()` was considered and rejected as equivalent but more code and more syscalls.

**Out of scope — TOCTOU:** a component swapped for a symlink between the check and the `rename()` is not prevented; PHP offers no `openat()`/dirfd-based traversal to close it. Exploiting it needs write access inside the cache directory, which already allows damaging the cache directly. The limitation is documented in the guard's docblock.

**Cost:** a few stat syscalls per cleanup, only on mutating requests — negligible.

### Logging refusals

| Refusal | Level |
|---|---|
| Path not under `<base>/` (prefix check) | `warn` |
| Path contains a `.`/`..` segment | `warn` |
| Real parent resolves outside `realpath($base)` (intermediate symlink) | `warn` |
| `realpath()` returns `false` (parent or base absent — nothing to delete) | silent (unchanged behaviour) |
| Path is the trash folder or inside it | silent (unchanged behaviour) |

Message format, consistent with the existing `cache trash rename failed — dir: …, reason: …` lines:

```
cache cleanup refused — dir: <dir>, reason: <dot segment | outside cache location | resolves outside cache location (<real parent>)>
```

- `$dir` is built from the request path only, never the query string, so this stays in line with #314.
- No rate limiting: request paths with `..` are normally normalised by Apache before reaching PHP, and `custom` targets come from configuration, so warn-flooding is not a realistic concern. Logging at `debug` instead was rejected — a misconfigured `custom` target would then silently never clean anything in production.

### Other path builders (`File`, `FileCache`)

- `File` (via `StaticFileHandler`) and `ResponseContentReader` are **already protected** by `RequestPathValidator`, which rejects any `..` or backslash — no change needed.
- `FileCache` body/meta **write** paths (`<base>/<requestPath>/<METHOD>/<hash>.*`) have no equivalent guard. This is out of scope here and tracked separately in #324, which will also decide whether to extract this issue's private guard into a shared helper.

### Scope

**In scope**

- The `CacheDirCleaner::deleteDir()` guard (private method): `.`/`..` segment rejection after the base, and the `realpath(dirname($dir))` vs `realpath($base)` comparison, in the order prefix → segments → realpath → trash → #316 `is_link()`.
- `warn` logging for the three suspicious refusals; absent-path and trash cases stay silent.
- Docblock updates (guard and class-level docs) describing the actual guarantee and the documented TOCTOU limitation.
- Unit tests.

**Out of scope**

- `CacheDirResolver` — unchanged; the guard is the single enforcement point.
- `FileCache` write paths — #324.
- `File` / `StaticFileHandler` — already covered by `RequestPathValidator`.
- A shared `FolderLocation::contains()` helper — decided in #324.
- Closing the check-to-`rename()` TOCTOU window.
- Rate limiting of refusal warnings.
- Load-time validation of `custom` cleanup targets. Placeholder values cannot contain `.` or `/` (`PlaceholderPattern` only allows `[A-Za-z0-9_-]+`, digits, or UUIDs), so a `..` in a `custom` target can only come from the literal configuration text and a load-time check on the template would be sufficient — but the runtime guard already covers it, so it is not added here.

### Edge cases

| Case | Behaviour |
|---|---|
| `%2e%2e` (encoded `..`) | Nothing in the proxy URL-decodes `requestPath()` (`parse_url(REQUEST_URI)`), so it reaches the filesystem as a literal directory name — harmless, accepted as an ordinary segment. |
| `...`, `..foo`, `foo..` | Valid names, not traversal. The check matches **whole segments only** (unlike `RequestPathValidator`'s substring check) and accepts them. |
| Empty segments (`a//b`) | Ignored by the segment check. |
| Base with trailing slash (`./cache/`) | Handled by the existing `rtrim` and by `realpath()`. |
| Base is `/` | `rtrim` yields `''`, which would make the prefix check accept everything. **Refused** with `warn` (reason: misconfigured cache location). |
| NUL byte in the path | PHP 8 filesystem functions throw `ValueError` on `\0`. **Refused** in the string-only segment check, before `realpath()` runs, so the guard never throws. |
| Relative base (`./cache`) | `realpath()` resolves it against the working directory, consistent with the rest of the proxy. |
| Case-insensitive filesystem (macOS dev) | The `realpath` comparison stays **exact** — lowercasing (as `CacheTrash` does) would wrongly accept `/x/CACHE` vs `/x/cache` on case-sensitive Linux. A mis-cased base on macOS may produce a logged refusal; production runs on Linux. |
| Backslash | A literal character on Linux, not a separator — no special handling. |

### Backward compatibility

| Setup | Today | After this change |
|---|---|---|
| `custom` target with a literal `..` (e.g. `/games/../users.json`) | Works, deletes `cache/users.json/GET` | Refused with `warn`; that cache is no longer cleared. Accepted breakage — undocumented, unlikely, and the `warn` points straight at the fix (`/users.json`). |
| Request path with an in-cache `..` (`/a/../b`) | Resolved directory deleted | Refused with `warn`. Legitimate clients don't send these. |
| Cache **base** is a symlink (e.g. Docker volume) | Works | Still works — both sides go through `realpath()`. |
| A cache **subdirectory** deliberately symlinked (e.g. to a bigger disk) | Cleanup follows the link | Refused with `warn`; those caches are no longer invalidated. Accepted: this is exactly problem 2, and the supported setup is to symlink the base instead. No opt-in flag is added. |
| Logs | — | New `warn` lines only on refusals. |

**Docs:** add a short note to the `CacheCleanupMiddleware` section of `docs/guides/tent/middlewares.md`: `custom` targets must not contain `.`/`..` segments, and cache subdirectories must not be symlinks (symlink the cache base instead).

### Security impact

This is a **defence-in-depth hardening**, not a vulnerability fix requiring an advisory. Impact ceiling: recursive deletion of a directory **named `GET`** (and its contents) wherever the proxy user can write — the final component is always `GET`, and #316 unlinks a final symlink rather than following it. No read, write, or code execution.

| Actor | Route | Today | Severity |
|---|---|---|---|
| Remote client | `..` in the request path (`clean()`) | Apache refuses `..` above the document root, so no escape in the shipped setup; an in-cache `..` only retargets another cache directory (forced re-fetch). Escape possible behind front ends that don't normalise like Apache. | Low, deployment-dependent |
| Remote client | `..` through `custom` placeholders | Impossible — placeholder values cannot contain `.` or `/`. | None |
| Operator | Literal `..` in a `custom` target | Trusted actor — footgun protection, not a security boundary. | Not a security issue |
| Local actor with write access inside the cache dir | Planted intermediate symlink | Deletes `<link target>/…/GET` trees. | Low–moderate, only with shared/writable cache volumes |
| Same, after this change | Symlink swapped between check and `rename()` (TOCTOU) | Narrow window, not closable in PHP; actor could already damage the cache directly. | Residual, documented |

Deployments not behind Apache benefit most. The main value is that the guard finally enforces the invariant it claims and that #316 relies on.

### Testing strategy

New file `source/tests/unit/lib/content/CacheDirCleaner/CacheDirCleanerGuardTest.php`, following the suite's conventions (real temp-dir tree per test, `LoggerInstance` mocks to assert log lines, real `symlink()` fixtures called directly as the existing symlink tests do). Fixture: base `tmp/<uniq>/cache` plus a sibling `tmp/<uniq>/outside` that must survive.

**Segment check**

- `cleanPath('/..')` with `tmp/<uniq>/GET` present → refused, directory survives, `warn` with reason "dot segment".
- `clean('collection', '/../x')` → refused.
- In-cache `..`: `cleanPath('/games/../users.json')` with `cache/users.json/GET` present → refused and that directory **survives** (no retargeting).
- `.` segment: `cleanPath('/./users.json')` → refused.
- `...`, `..foo`, `foo..`, `%2e%2e` segments → accepted and deleted.
- Path containing `\0` → refused with `warn`, no `ValueError` thrown.
- Base containing `..` (`cache/sub/..`) → cleanup still works (only the part after the base is inspected).
- Base with a trailing slash → works.
- Base `/` → refused with "misconfigured cache location" `warn` (safe: refusal happens before any filesystem access).

**`realpath` check**

- Intermediate symlink `cache/link` → `outside`, with `outside/x/GET` present, `cleanPath('/link/x')` → refused, directory survives, `warn` "resolves outside cache location" including the real parent.
- Base is a symlink (`cachelink` → `realcache`) → deletion succeeds, no `warn`.
- Symlinked root inside a symlinked base → link unlinked, target untouched, no `warn` (#316 preserved).
- Parent absent → no-op, nothing logged.
- Base absent → no-op, nothing logged.
- `cleanPath('/')` → `cache/GET` deleted (the "equals" case).

**Existing tests**

- Rewrite `CacheDirCleanerCleanTest::testCleanDoesNotDeleteDirOutsideBaseLocation` and `CacheDirCleanerCleanPathTest::testCleanPathDoesNotDeleteDirOutsideBaseLocation`: today they put the `..` in the **base** (`$cacheDir/users/../..`), which passes the prefix check, and only pass because `<outside>/GET` never exists. Rewrite them to put the `..` in the path and to create `<outside>/GET`, so they genuinely exercise the guard.
- Trash-rejection, rename and orphan-sweep tests stay unchanged and must keep passing.
- `CacheDirResolver` tests are unchanged (resolver out of scope).

## Benefits

- The guard finally enforces the invariant it documents ("only directories under the configured cache location are ever deleted"), which #316 relies on.
- Closes `..` traversal and intermediate-symlink escapes with a single, private, well-tested check at the point of deletion.
- Misconfigured `custom` targets and tampered cache directories become visible through `warn` logs instead of silently deleting the wrong directory.
