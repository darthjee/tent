# Architecture

## Source Layout

```
source/
  source/
    index.php               ← Entry point (after Apache rewrite)
    loader.php              ← Manual class loader (require_once per file)
    lib/
      Configuration.php     ← Static rule registry
      service/
        RequestProcessor.php
      models/
        Rule.php
        ProcessingRequest.php
        Response.php
      common/               ← Base model (SimpleModel)
      content/              ← Cache and file abstractions (Cache, File, FileCache, ResponseContent)
      exceptions/           ← Custom exceptions (FileNotFoundException, InvalidFilePathException)
      http/                 ← HTTP client abstraction (CurlHttpClient, HttpClientInterface)
      utils/                ← Utility helpers (CacheFilePath, ContentType, CurlUtils, FileUtils, SafeRemover, StringUtils, HttpCodeMatcher)
      validators/           ← Input validation (RequestPathValidator)
      request_handlers/     ← DefaultProxyRequestHandler, ProxyRequestHandler, StaticFileHandler, MissingRequestHandler
      middlewares/          ← FileCacheMiddleware, CacheStalenessMiddleware, SetHeadersMiddleware, SetPathMiddleware, RenameHeaderMiddleware, RedirectMiddleware
      matchers/             ← ExactRequestMatcher, BeginsWithRequestMatcher, EndsWithRequestMatcher, RegexRequestMatcher,
                              StatusCodeMatcher, ResponseHeaderMatcher, RequestMethodMatcher, NegativeMatcher
      log/                  ← Logger (static facade), LoggerInstance (default, reads LOG_LEVEL), NullLoggerInstance (test double)
  tests/
    unit/
    integration/

dev/
  api/                      ← Mock PHP backend for testing
    source/
      index.php
      loader.php
    lib/api_dev/endpoints/
    migrations/             ← Numbered .sql files (idempotent, re-run each time)
  frontend/                 ← React 19 + Vite dev frontend
    assets/js/
      components/
      clients/
    spec/                   ← Jasmine tests

docker_volumes/
  configuration/            ← User-defined rules (NOT version-controlled)
    configure.php           ← Or rules/ subdirectory
  cache/                    ← Cached responses (mounted into container)
```

---

## Key Components

### `Configuration` (`source/source/lib/Configuration.php`)

Static rule registry. Rules are defined via `Configuration::buildRule()` in `docker_volumes/configuration/` (not version-controlled). Also exposes `Configuration::reset()` used in tests to clear rules between test cases.

### `RequestProcessor` (`source/source/lib/service/RequestProcessor.php`)

Iterates through registered `Rule` objects and invokes the handler of the first matching rule.

### `Rule` (`source/source/lib/models/Rule.php`)

Holds a list of matchers (URI patterns, HTTP methods) and a handler reference. Matched via `Rule::match()`.

Handler construction (which wires up any handler-specific default middlewares) happens before `RequestHandler::build()` applies rule-level middlewares: the optional `prependMiddlewares` rule key is inserted right after construction — before those defaults — while `middlewares` keeps being appended after them, unchanged.

### Handlers (`source/source/lib/request_handlers/`)

| Type | Class | Notes |
|------|-------|-------|
| `default_proxy` | `DefaultProxyRequestHandler` | **Preferred.** Auto-adds `RenameHeaderMiddleware`, `SetHeadersMiddleware`, `FileCacheMiddleware`. |
| `proxy` | `ProxyRequestHandler` | Lower-level; no automatic middlewares. Use for fully custom stacks. |
| `static` | `StaticFileHandler` | Serves files from a directory. Returns 403/404 for invalid/missing paths. |
| _(fallback)_ | `MissingRequestHandler` | Used internally when no rule matches. Always returns 404. |

See [`request-handlers.md`](../request-handlers.md) for full options and examples.

### Middlewares (`source/source/lib/middlewares/`)

Implement `processRequest(ProcessingRequest)` and/or `processResponse(Response)`. Built-ins:

| Class | Purpose |
|-------|---------|
| `FileCacheMiddleware` | Cache responses to disk; serve from cache on subsequent requests |
| `CacheStalenessMiddleware` | Serve a stale `FileCacheMiddleware` hit while triggering a background refresh; pairs with `FileCacheMiddleware` (same `location`) and acts in the response phase on cache hits |
| `SetHeadersMiddleware` | Set or override request headers |
| `SetPathMiddleware` | Rewrite the request path |
| `RenameHeaderMiddleware` | Rename a request header (copy value to new name, remove original) |
| `RedirectMiddleware` | Rewrite request path using regex and return a 302 redirect |

See [`creating-middlewares.md`](../creating-middlewares.md) for how to build custom middlewares.

### Matchers (`source/source/lib/matchers/`)

| Class | Matches on |
|-------|-----------|
| `ExactRequestMatcher` | Exact URI string |
| `BeginsWithRequestMatcher` | URI prefix |
| `EndsWithRequestMatcher` | URI suffix |
| `RegexRequestMatcher` | URI regex pattern |
| `StatusCodeMatcher` | HTTP response status code (exact or pattern like `"2xx"`) |
| `ResponseHeaderMatcher` | Response header value |
| `RequestMethodMatcher` | HTTP method (GET, POST, …) |
| `NegativeMatcher` | Inverts any other matcher |

See [`adding-request-matchers.md`](../adding-request-matchers.md) for how to add new matchers.

### Content (`source/source/lib/content/`)

Cache and file abstractions used by `FileCacheMiddleware`: `Cache`, `File`, `FileCache`, `CacheHit`, `ResponseContent`, `HeaderFilter`, `ExcludedHeaderFilter`, `AllowedHeaderFilter`, and `HeaderFilterBuilder`.

The request-middleware chain stops at the first middleware which sets a response, so anything that has to react to a cache hit runs in the response phase, which visits every middleware of the handler. On a hit (only), `FileCacheMiddleware` records a `CacheHit` on `ProcessingRequest` (not on `RequestInterface`): the served `FileCache` entry plus its `HeaderFilter`. `CacheStalenessMiddleware::processResponse()` acts only on responses whose request carries it, and its `BackgroundRefresher` rewrites that entry with that filter; its own header filter options are deprecated and ignored. `FileCacheMiddleware::processResponse()` never writes a response back into the entry it was served from, so a refresh eviction is not undone by middleware ordering. On SAPIs without `fastcgi_finish_request()` (including the shipped mod_php image) the refresh runs synchronously.

Refreshes of an entry are debounced by its `.refreshing` sentinel, which holds the time it was written and expires, so that a refresh whose process died does not block the entry. A sentinel older than `sentinel_ttl_seconds` (default 60s, twice the refresh cURL timeout and independent of `maxAgeSeconds`; `<= 0` never expires) is overwritten, the refresh is scheduled again and a `warn` is logged. Its age comes from the stored timestamp, falling back to `filemtime()` when that is missing, invalid or more than one TTL ahead; a sentinel which cannot be dated keeps debouncing. Only regular files are read, at most 32 bytes, so a FIFO or a device is never opened. The debounce is not a lock, and a finishing refresh removes the sentinel unconditionally, even a successor's (#333).

`CacheDirCleaner` (with `CacheDirResolver`) deletes cache directories on behalf of `CacheCleanupMiddleware`. Both it and `FileCache::remove()` tolerate other writers deleting the same entries concurrently, by removing through `SafeRemover`: anything already gone is success, while a real failure is logged and never thrown. `CacheDirCleaner` restarts a walk interrupted by a vanished directory (3 attempts in total) and unlinks symbolic links without following them.

Invalidation is rename-then-delete: `CacheDirCleaner` first moves the target directory, with a single `rename()`, into `<base>/.tent-trash/<unix-ts>.<16 hex chars>` and then deletes that private tree. Readers see the invalidation atomically, and new cache writes never land in the tree being deleted. The trash folder is created on demand and never removed. A symbolic link standing in place of the target is unlinked without being renamed.

- A lost rename race (the source is gone after the failed `rename()`) counts as already gone: nothing is logged and nothing is walked.
- A real rename failure, or an unusable trash (a link, a regular file, or a folder that cannot be created), logs a `warn` and falls back to deleting the directory in place.
- The `cache cleared — dir: <original path>` debug line is logged when this call won the rename (or, on the fallback path, removed the directory).
- After its own deletion, each `clean()` / `cleanPath()` call sweeps at most one orphan: a trash entry older than a 10-minute grace period (by the timestamp in its name), claimed by renaming it to a new entry name before deleting it (`cache trash orphan swept — entry: <name>` at `debug`). Names not matching the entry pattern are left alone.

`CacheTrash` is the single source of truth for the folder name, the entry names and the reserved path rule shared by `FileCache` and `CacheDirCleaner`: a resolved cache path which, after lexical normalization (`FileUtils::normalizePath()`) and compared case-insensitively, equals or lies under `<base>/.tent-trash` is reserved. `FileCache` never reports a hit, stores or removes anything for it, and a cleanup target resolving there is a no-op.

Unsafe paths have one lexical definition, `FileUtils::hasSafeSegments()`: a segment which is exactly `.` or `..`, or a NUL byte. `FileCache` applies it to the request path and method (rewritten paths included) and makes such an entry inert, like a trash entry: no read, write, removal or directory creation, and a `warn` is logged, while the request is still proxied. `CacheDirCleaner` refuses such paths up front with a `warn`, and `CacheDirResolver` returns `null` for them as defence-in-depth.

Overlong paths make an entry inert in the same way, through a lexical byte-length check in the `FileCache` constructor, run after the unsafe check: a request path segment, the method or a cache file name above 255 bytes, or a body/meta path above 4094 bytes (PHP's filesystem functions reject 4095, one byte short of what the kernel takes with `PATH_MAX` 4096). The location counts only towards the path total. Both file limits reserve `FileCache::DERIVED_SUFFIX_HEADROOM` (the `writeAtomically()` temporary suffix), so any longer suffix appended to a cache file path requires a larger headroom; a spec guards `CacheStalenessMiddleware::REFRESH_SENTINEL_SUFFIX` against it. A single `warn` (`reason: path too long`, path and method truncated to 200 bytes) is logged per request, tracked on `ProcessingRequest`. Any other directory creation failure is silenced and logged as `cache store skipped — …`, so the cache layer never prints a PHP warning into the response.

Symbolic links are checked on writes, not on reads. Before and after creating the entry directory, `FileCache::store()` checks with `FolderLocation::contains()` (shared with `CacheDirCleaner`) that it, or its deepest existing ancestor, resolves inside the real cache location; otherwise nothing is written and `cache store skipped — …` is logged at `warn`. The location itself may be a symbolic link, or not exist yet. The body, the metadata (written last, as the commit marker) and the `.refreshing` sentinel are written by `FileUtils::writeAtomically()` (temporary file, `chmod 0644`, `rename()`), which replaces a link standing at the target. Cache directories, `.tent-trash` included, are created with mode `0755`, which the umask may only reduce. The read path makes no `realpath()` check, so the cache directory must still be writable only by tent. Neither the sentinel nor `FileCache::remove()` is containment-checked, so both can reach outside through a directory link, and the window between the check and the write (TOCTOU) is accepted.

Cache hits are read through `CacheContentReader` (`source/source/lib/service/`), which only accepts a `FileCache` and skips `RequestPathValidator`, as the entry has already screened the path. Static files are still read through `ResponseContentReader`, which applies the validator.

### HTTP Client (`source/source/lib/http/`)

Thin abstraction over cURL: `HttpClientInterface`, `CurlHttpClient`, and `CurlHttpExecutor`. Used by proxy handlers to forward requests upstream. `UpstreamFailureNormalizer` decorates every proxy client and maps transport failures to `502` (or `504` on cURL timeout) instead of an empty `200`; `connect_timeout`/`timeout` options configure the default `CurlHttpClient`. `BackgroundRefresher` uses an undecorated client with the default timeouts: a result with a non-zero cURL `errno` counts as a failed refresh, which never replaces or evicts the cache entry.

### Utils (`source/source/lib/utils/`)

Internal helpers: `CacheFilePath` (cache key generation), `ContentType` (MIME mapping), `CurlUtils`, `FileUtils`, `StringUtils`, `HttpCodeMatcher` (wildcard HTTP code matching like `"2xx"`), and `SafeRemover` (concurrency-tolerant file and directory removal).

`SafeRemover::removeFile()` / `removeDir()` return a `RemovalResult` enum: `Removed`, `AlreadyGone` (the path no longer exists after the failed operation; nothing logged) or `Failed` (the path still exists). On `Failed`, `SafeRemover` itself logs `cache removal failed — file|dir: <path> — <error>` at `warn` level, so callers do not log removal failures.

### Validators (`source/source/lib/validators/`)

`RequestPathValidator` — validates the resolved path for `StaticFileHandler` (guards against path traversal, returns 403/404 as appropriate).

### Exceptions (`source/source/lib/exceptions/`)

`FileNotFoundException` and `InvalidFilePathException` — thrown by `StaticFileHandler` and caught to produce the correct HTTP error response.

### Logger (`source/source/lib/log/`)

| Class | Purpose |
|-------|---------|
| `Logger` | Static facade — call `Logger::debug()`, `Logger::info()`, `Logger::warn()`, `Logger::error()` from any production code |
| `LoggerInstance` | Default implementation; reads `LOG_LEVEL` env var on construction and filters messages below the threshold |
| `NullLoggerInstance` | Silent test double; inject via `Logger::setInstance(new NullLoggerInstance())` in `setUp()` |

`LOG_LEVEL` accepted values (ascending precedence): `debug`, `info`, `warn`, `error`. Default: `debug` (log everything).

#### Debug log messages

When `LOG_LEVEL=debug`, Tent emits `Logger::debug()` calls with a standardized `[status] - reason` format.

| Cause | Log message format | Source |
|-------|--------------------|--------|
| No rules matched the request | `[404] - no rules matched — method: <METHOD>, uri: <URI>` | `RequestProcessor::getRequestHandler()` |
| Static file not found or path is a directory | `[404] - static file not found — uri: <URI>, resolved path: <ABSOLUTE_PATH>` | `StaticFileHandler::processsRequest()` |
| Response from upstream proxy | `[<STATUS>] - upstream response — method: <METHOD>, uri: <URI>, upstream: <UPSTREAM_URL>` | `ProxyRequestHandler::processsRequest()` |
| Response served from file cache | `[<STATUS>] - serving from cache — uri: <URI>` | `FileCacheMiddleware::processRequest()` |

Note: the proxy and cache entries log for **every** response status, not just 404.

In the upstream proxy entry, `<UPSTREAM_URL>` is the upstream base URL plus the request path, **without the query string** (query strings may carry credentials). The upstream request itself still forwards the query string.

#### Apache access log

The Apache access log is a separate stream from the PHP `Logger`. Apache writes it after PHP returns, so neither `LOG_LEVEL` nor any PHP class or `configuration/` rule affects it. It is configured by the published `darthjee/tent` image:

- `TENT_ACCESS_LOG_FORMAT` selects a named preset: `safe` (default when unset or empty; no query string, no `Referer`) or `combined` (the stock Debian format). Any other value stops Apache from starting.
- `*.conf` files mounted under `/etc/apache2/tent.d/` are included after the presets, so a drop-in always wins over the variable.

See the [Tent reference guide](../guides/tent/reference.md) for the consumer-facing documentation.

#### PHP error output

`ResponseSender` emits status and headers only after the whole pipeline has run, so a PHP diagnostic printed during a request would corrupt the response: body prefixed, headers dropped, status forced to 200. The published `darthjee/tent` image therefore logs diagnostics instead of displaying them:

- `/usr/local/etc/php/conf.d/tent.ini` (source: `dockerfiles/tent/php/tent.ini`) sets `display_errors = Off`, `log_errors = On` and the upload limits. `error_log` is left unset, so under mod_php engine diagnostics go to the Apache error log, which is linked to stderr. That is the same stream as the PHP `Logger`'s `error_log()` output.
- `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf` (source: `dockerfiles/tent/apache/00-tent-0-php-log-level.conf`) sets `LogLevel warn php:info`. Without it, deprecations are dropped. The file name must sort before `00-tent-access-log.conf`, which includes `tent.d`, so that a drop-in there can still override the level.

| Prefix on stderr | What is logged with it |
|------------------|------------------------|
| `[php:error]` (or higher) | Fatal errors, including uncaught exceptions |
| `[php:warn]` | Warnings |
| `[php:notice]` | Notices, and every `Logger` message, whatever its own level |
| `[php:info]` | Deprecations |

`LOG_LEVEL` filters only Tent's own messages, never engine diagnostics.

Consumers override PHP settings with a `/usr/local/etc/php/conf.d/zz-<name>.ini` file (mounting over `tent.ini` replaces the whole production configuration) and the log level with a `*.conf` file in `/etc/apache2/tent.d/`.

**Development and production differ on purpose.** `dev_tent-base`, `dev_tent` and `tent-test` do not use these settings and keep `display_errors` on, for local debugging and PHPUnit output. `scripts/build_docker_image.sh` checks both sides when it verifies `tent`, `tent-test` and `dev_tent-base`; for `tent` it also checks the log-level file and runs a request which raises a warning and a deprecation.

**Not covered:** output which is not a PHP diagnostic (a stray `echo`, a byte order mark) still reaches the client before the headers ([#335](https://github.com/darthjee/tent/issues/335)), and source-tarball deployments use their own PHP configuration.

See [PHP error output](../guides/tent/reference.md#php-error-output) in the reference guide for the consumer-facing documentation.

---

## Configuration Rules Pattern

Rules live in `docker_volumes/configuration/configure.php` (or individual files under `docker_volumes/configuration/rules/`). This directory is **not version-controlled**.

```php
Configuration::buildRule([
  'handler' => [
    'type' => 'default_proxy',  // 'default_proxy' preferred; also 'proxy', 'static'
    'host' => 'http://api:80'
  ],
  'matchers' => [
    ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact'],
    // type: 'exact', 'begins_with', 'ends_with', 'regex'; method: any HTTP verb
  ],
  'prependMiddlewares' => [
    // optional; same shape as 'middlewares' below, but inserted BEFORE
    // the handler's own default middlewares (e.g. default_proxy's
    // RenameHeaderMiddleware/SetHeadersMiddleware/FileCacheMiddleware)
    [
      'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
      'headers' => ['X-Api-Key' => 'secret']
    ]
  ],
  'middlewares' => [
    // appended AFTER the handler's default middlewares (unchanged behavior)
    [
      'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
      'location' => './cache',
      'matchers' => [
        ['class' => 'Tent\\Matchers\\StatusCodeMatcher', 'httpCodes' => [200]]
      ]
    ],
    [
      'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
      'headers' => ['Host' => 'backend.local']
    ]
  ]
]);
```

---

## Class Loading (`loader.php`)

There is **no Composer PSR-4 autoload** for runtime classes. Every new class file must be added explicitly via `require_once __DIR__ . '/lib/...'`:

- Tent app → `source/source/loader.php`
- Dev API → `dev/api/source/loader.php`

Rules: one `require_once` per file, dependency-first ordering (interfaces/base classes before concrete implementations), grouped by domain (`middlewares`, `models`, `matchers`, etc.).

---

## Dev API (`dev/api/`)

A mock PHP backend used for testing the proxy.

- Entry point: `dev/api/source/index.php` — routes registered with `Configuration::add('METHOD', '/path', EndpointClass::class)`
- Endpoint classes extend `Endpoint` and implement `handle()` returning a `Response`
- To add an endpoint: create the class in `lib/api_dev/endpoints/`, add `require_once` in `loader.php`, register in `index.php`
- DB migrations: numbered `.sql` files in `dev/api/migrations/`, executed in order, re-run each time (use idempotent SQL)

---

## Dev Frontend (`dev/frontend/`)

React 19 app with Vite, TanStack Query, Bootstrap 5. Tests use Jasmine in `spec/`.

Controlled by `FRONTEND_DEV_MODE` in `.env`:

- `true`: Tent proxies to Vite dev server (port 8030) with HMR — changes reflect immediately
- `false`: Tent serves built static files from `dev/frontend/dist/`

Structure: components in `assets/js/components/`, API clients in `assets/js/clients/`, tests in `spec/`.

---

## Testing Conventions

- **PHP**: Use `Configuration::reset()` in `setUp()` to clear rules between tests. Test handler behavior via `Rule::match()` and handler execution separately.
- **Frontend**: Jasmine specs in `spec/`, mirroring source structure.
- **Integration tests**: Run against live containers; do not mock the database.

---

## Key Files

| File | Purpose |
|------|---------|
| `source/source/lib/service/RequestProcessor.php` | Core routing logic |
| `source/source/lib/Configuration.php` | Rule registry |
| `source/source/lib/request_handlers/RequestHandler.php` | Handler base with middleware application |
| `source/source/loader.php` | Manual class loading — update when adding classes |
| `docker_volumes/configuration/` | User-defined rules (not version-controlled) |
| `docs/` | Implementation guides for middlewares, matchers, handlers |
