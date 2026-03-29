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
      request_handlers/     ← DefaultProxyRequestHandler, ProxyRequestHandler, StaticFileHandler, MissingRequestHandler
      middlewares/          ← FileCacheMiddleware, SetHeadersMiddleware, SetPathMiddleware, RenameHeaderMiddleware
      matchers/             ← ExactRequestMatcher, BeginsWithRequestMatcher, EndsWithRequestMatcher,
                              StatusCodeMatcher, ResponseHeaderMatcher, RequestMethodMatcher, NegativeMatcher
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

### Handlers (`source/source/lib/request_handlers/`)

| Type | Class | Notes |
|------|-------|-------|
| `default_proxy` | `DefaultProxyRequestHandler` | **Preferred.** Auto-adds `RenameHeaderMiddleware`, `SetHeadersMiddleware`, `FileCacheMiddleware`. |
| `proxy` | `ProxyRequestHandler` | Lower-level; no automatic middlewares. Use for fully custom stacks. |
| `static` | `StaticFileHandler` | Serves files from a directory. Returns 403/404 for invalid/missing paths. |
| _(fallback)_ | `MissingRequestHandler` | Used internally when no rule matches. Always returns 404. |

See [`request-handlers.md`](request-handlers.md) for full options and examples.

### Middlewares (`source/source/lib/middlewares/`)

Implement `processRequest(ProcessingRequest)` and/or `processResponse(Response)`. Built-ins:

| Class | Purpose |
|-------|---------|
| `FileCacheMiddleware` | Cache responses to disk; serve from cache on subsequent requests |
| `SetHeadersMiddleware` | Set or override request headers |
| `SetPathMiddleware` | Rewrite the request path |
| `RenameHeaderMiddleware` | Rename a request header (copy value to new name, remove original) |

See [`creating-middlewares.md`](creating-middlewares.md) for how to build custom middlewares.

### Matchers (`source/source/lib/matchers/`)

| Class | Matches on |
|-------|-----------|
| `ExactRequestMatcher` | Exact URI string |
| `BeginsWithRequestMatcher` | URI prefix |
| `EndsWithRequestMatcher` | URI suffix |
| `StatusCodeMatcher` | HTTP response status code (exact or pattern like `"2xx"`) |
| `ResponseHeaderMatcher` | Response header value |
| `RequestMethodMatcher` | HTTP method (GET, POST, …) |
| `NegativeMatcher` | Inverts any other matcher |

See [`adding-request-matchers.md`](adding-request-matchers.md) for how to add new matchers.

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
    // type: 'exact', 'begins_with', 'ends_with'; method: any HTTP verb
  ],
  'middlewares' => [
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
