# Issue: FileCache: never read or write outside the cache root through dot segments

## Description

Cache paths are built by joining the configured cache root with request-path segments: `CacheDirResolver` (cleanup targets) and `FileCache::basePath()` (reads/writes) use `FileUtils::getFullPath()`, which only trims slashes, and segments are only filtered for emptiness (`array_filter`). `.` and `..` segments are kept as-is.

The **delete side has already been hardened in #320**: `CacheDirCleaner::isSafelyScoped()` now refuses any `.`/`..` segment or NUL byte after the cache location, and checks that the `realpath()` of the parent stays under the real cache location. `FileUtils::normalizePath()` (lexical normalization) was added there too.

The **read/write side is still unprotected**: `FileCache::basePath()` builds `<base>/<requestPath>/<METHOD>` and `mkdir(..., 0777, true)`s it before writing.

`Request::requestPath()` is `parse_url(REQUEST_URI)['path']`, not URL-decoded — so `%2e%2e` stays a literal name, but raw `..` segments reach tent unless the web server (Apache in the shipped images) normalizes them first; this has not been verified. Middlewares may also rewrite the request path from configuration.

Spotted while refining #319.

### Scope

In scope:

- `FileCache`: never read, write, or create directories outside the cache root, whatever the request path.
- `CacheDirResolver`: defence-in-depth — do not emit cleanup targets containing dot segments.
- Verifying (and documenting) whether `..` / `.` / `%2e%2e` segments can reach `Request::requestPath()` in the shipped Apache images. The fix does not depend on the answer.
- Specs for the read/write side.

Out of scope:

- The delete-side guard in `CacheDirCleaner` (done in #320).
- Other path builders (`File`, `CacheTrash`, `StaticFileHandler`): static files already go through `RequestPathValidator`, and the others do not take request input.

## Problem

A request path containing `..` segments (e.g. `/../elsewhere`) would make `FileCache` resolve to `<base>/../elsewhere/GET`, reading from, creating directories in, and writing into a location outside the cache root.

`CacheDirResolver` would likewise produce such paths as cleanup targets; they are now refused by `CacheDirCleaner` (#320), but the resolver should not produce them in the first place.

### Threat

- **Dot segments are the threat this issue closes.** If `..` reaches tent, any unauthenticated client could make the tent process `mkdir -p` an arbitrary directory it can create and write `<hash>.body.dat` / `<hash>.meta.json` files there (directory attacker-controlled, file names not, content from the upstream response), and serve cache hits from outside the root. A lexical check (dot segments and NUL bytes) closes this completely, without filesystem access.
- **Symlinks inside the cache directory are out of scope.** Planting one requires write access to the cache volume, which already allows poisoning the cache directly — the same threat model #320 accepted for its TOCTOU limitation. No `realpath()` check is added to the read/write path, keeping the hot path free of extra `stat` calls.
- Document this trust boundary: the cache directory must be writable only by tent.

## Expected Behavior

### Behavior on a dot segment

When the request path has a `.` or `..` segment (or a NUL byte):

- **`FileCache` bypasses the cache** for that request: no cache read, no cache write, no directory created. The request is proxied to upstream as usual — tent forwards the path unchanged anyway, so interpreting it is upstream's concern. A `warn` line is logged.
- **`CacheDirResolver` returns no target** (`null`, i.e. no cleanup), as `resolveEntity()` already does for paths it cannot resolve.

Alternatives rejected:

- **Lexical normalization** (`/a/../b` → `/b`): upstream received the raw path and may resolve it differently, so its response would be stored under — and later served for — `/b`. That opens a cache-poisoning route. It would also need a rule for `..` above the root.
- **Answering 400**: rejects the whole request, not just its caching, changing proxy semantics beyond this issue's scope; and middlewares may rewrite the path after request entry. Strict rejection at the edge could be a separate follow-up.

### Edge cases

- **Still cacheable** (whole-segment match only): `...`, `..foo`, `a..b`, and dot-prefixed names such as `.git` or `.htaccess`.
- **Literal, harmless** (tent does not URL-decode; on Linux these are plain name characters): `%2e%2e`, `%2F`, `%00`, `\`. Apache's default `AllowEncodedSlashes Off` rejects `%2f` before PHP (to confirm during the deployment verification).
- **Empty segments** (`/a//b`): unchanged; the filesystem collapses them and the hash keeps entries apart.
- **NUL bytes, relative or trailing dot segments** (`a/../b`, `/a/..`, `/a/.`): cannot all come from the request line, but a middleware rewrite can produce them. Rejected, since the check runs on the final `requestPath()`.
- **The HTTP method is a path segment too**: `FileCache::basePath()` builds `<base>/<path>/<METHOD>`, and HTTP method tokens may contain `.`, so `..` is a syntactically valid method (`..` on `/` → `<base>/..`). Whether this is reachable depends on whether `FileCache` is built before the `requestMethods` filter (unclear for `CacheStalenessMiddleware`). The check therefore runs on the whole relative part — path **and** method — not the path alone.
- **Trash prefix** (`/.tent-trash/...`): already inert via #319's trash check; combines with the unsafe-path flag.
- **Out of scope**: segments longer than `NAME_MAX` make `mkdir` fail — a robustness concern, not traversal; tracked in #326.

## Solution

1. Verify with `curl --path-as-is` whether `..` / `.` / encoded (`%2e%2e`) segments can reach `Request::requestPath()` in the shipped image, and record the outcome in the PR description (see "Deployment verification").
2. Regardless of the answer, extract `FileUtils::hasSafeSegments()` from `CacheDirCleaner`, then make `FileCache` bypass the cache (and log a warning) when the request path or method has a dot segment or NUL byte (lexical check only), make `CacheDirResolver` return no target for such paths (`resolveExact()` becomes `?string`), and make `CacheDirCleaner::clean()`/`cleanPath()` refuse unsafe paths up front with a `warn` log.
3. Add a "Path safety" subsection to `docs/guides/tent/cache-configuration.md`: paths never cached and why, and the cache directory trust boundary (writable only by tent; symlinks inside it are not defended against).
4. Add specs proving that a traversal attempt never reads or writes outside the cache root (see "Testing strategy").

### Where to enforce

- **One shared definition of "unsafe"**: move #320's private `CacheDirCleaner::hasSafeSegments()` (whole-segment match on `.`/`..`, plus NUL byte) to a public `FileUtils::hasSafeSegments()`, next to `FileUtils::normalizePath()`. `CacheDirCleaner`, `FileCache` and `CacheDirResolver` all use it.
- **`FileCache`**: reuse the existing inert mechanism. Since #319, an entry resolving into the trash is flagged `$reserved` in the constructor, making `exists()` false, `timestamp()` null, and `store()`/`remove()` no-ops — callers (`FileCacheMiddleware`, `CacheStalenessMiddleware`) already handle that. Rename the flag (e.g. `$inert`) and set it when the entry is in the trash **or** the request path has unsafe segments; document both causes in the docblock. Log the warning for the unsafe-path case. The check runs in the constructor, on `$request->requestPath()`, so it covers paths rewritten by middlewares.
- **`CacheDirResolver`**: check segments up front and return `null`.

Alternatives rejected:

- **Reusing `RequestPathValidator`**: it rejects any `..` substring (so legit names like `a..b` or `..foo` would lose caching) and backslashes; changing it would affect the static-file path.
- **Checking once at request entry**: misses paths rewritten by middlewares afterwards, and the per-request bypass decision belongs to the cache layer.

### Deployment verification

The shipped image is `php:8.4-apache` (mod_php), with `.htaccess` rewriting everything to `index.php`; tent reads `REQUEST_URI`. Expected (to be confirmed):

| Request | Expected Apache behavior | Expected `requestPath()` |
|---|---|---|
| `/../x` (above root) | 400 | — |
| `/a/../b` | normalizes its internal `r->uri`, but `REQUEST_URI` is raw | `/a/../b` (exploitable today) |
| `/a/./b` | same | `/a/./b` |
| `/a/%2e%2e/b` | decodes `%2e` for its own mapping only | `/a/%2e%2e/b` (literal name, harmless — tent does not URL-decode) |

How to verify: in the `tent` image, send each shape (plus `//` and `%2f`) with `curl --path-as-is` — without it curl normalizes dot segments client-side and proves nothing — and record the raw `requestPath()` tent sees. Put the resulting table in the PR description.

Documentation: add a short "Path safety" subsection to `docs/guides/tent/cache-configuration.md` stating which request paths are never cached and why, the cache-directory trust boundary (see below), and one sentence on what Apache passes through. The detailed Apache table stays in the PR only, so the docs don't depend on Apache internals.

### Backward compatibility

- **Runtime behavior**: browsers and most HTTP clients normalize dot segments before sending, so legit traffic essentially never carries them. Such requests, cached before, are now proxied uncached. Names like `...` or `..foo` stay cached, so no false-positive regression.
- **Existing cache data**: `/a/./b` was physically stored under `<base>/a/b/GET` (with a different hash than `/a/b`); those files become never-read orphans, removed by the next invalidation of `/a/b`. Entries previously written *outside* the root by a `..` request cannot be located by tent: mention in the PR that, if exploitation is suspected, operators can look for `GET/<hash>.body.dat` / `.meta.json` files outside the cache root.
- **Code/API**:
  - `FileCache::$reserved` → `$inert`: private, no impact.
  - `FileUtils::hasSafeSegments()`: new public method, additive (the private copy in `CacheDirCleaner` is removed).
  - **`CacheDirResolver::resolveExact()`** (public) changes its return type from `string` to `?string`. Its only internal caller, `CacheDirCleaner::cleanPath()`, must handle `null`, as `clean()` already does for `resolve()`. Call it out in the PR for extension code calling it directly.
- **Keep the refusal warning**: today a cleanup target with `..` reaches `CacheDirCleaner::isSafelyScoped()`, which logs a `warn` ("dot segment"). With the resolver returning `null` first, that warning would be lost — and in `clean()` an unsafe-path `null` is indistinguishable from the harmless "not an entity" `null`. So `CacheDirCleaner::clean()` and `cleanPath()` check `FileUtils::hasSafeSegments($path)` up front, log the `warn` and return; the resolver still returns `null` as defence-in-depth.

### Testing strategy

1. **`FileUtils::hasSafeSegments()` unit specs**: rejects `.`, `..`, leading/trailing/relative dot segments and NUL bytes; accepts `...`, `..foo`, `a..b`, `.git`, `%2e%2e`, `\`. The existing `CacheDirCleaner` specs from #320 keep passing.
2. **`FileCache` specs** with a temporary cache root, for traversal paths (`/../x`, `/a/../../x`) and a `..` method: `exists()` is false; `store()` creates nothing inside or outside the root (asserted on a sibling directory of the root); a file planted outside the root at the traversal target is never served; a `warn` is logged.
3. **`CacheDirResolver` / `CacheDirCleaner` specs**: `resolve()`/`resolveExact()` return `null`; `clean()`/`cleanPath()` log the `warn` and touch nothing.
4. **Middleware-level spec**: a `..` request through `FileCacheMiddleware` is proxied normally and nothing is cached.
5. **No automated Apache test**: the `curl --path-as-is` verification stays a manual step recorded in the PR.

## Benefits

- Closes an unauthenticated arbitrary-directory write (and out-of-root cache read) on the cache hot path, with a purely lexical check and no extra filesystem calls.
- One shared definition of an unsafe path (`FileUtils::hasSafeSegments()`) for reads, writes and cleanup.
- No behavior change for legit traffic; operators get a documented trust boundary for the cache directory.
