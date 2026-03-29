# AGENTS.md

Shared guidance for AI agents (Claude Code, GitHub Copilot, etc.) working in this repository.

## What Is Tent

Tent is a PHP-based intelligent reverse proxy server that routes HTTP requests to backend services, caches responses, or serves static files based on configuration rules. It runs on Apache with PHP inside Docker containers.

## Commands

**Always use Docker Compose (v2 syntax). Never run commands directly on the host.**

Use `docker compose run` for one-off commands and `docker compose exec` only when the service is already running via `docker compose up`.

### Backend (PHP)
```bash
docker compose run --rm tent_tests composer tests              # All tests
docker compose run --rm tent_tests composer tests:unit        # Unit tests only
docker compose run --rm tent_tests composer tests:integration # Integration tests
docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/path/to/TestFile.php  # Single test file
docker compose run --rm tent_tests composer lint              # Check code style
docker compose run --rm tent_tests composer lint:fix          # Auto-fix code style
docker compose run --rm tent_tests composer docs              # Generate PHPDoc
docker compose run --rm tent_tests composer complexity        # PHPMD analysis
```

### Frontend (React/Vite)
```bash
docker compose run --rm frontend_dev npm test          # Jasmine tests
docker compose run --rm frontend_dev npm run lint      # ESLint
docker compose run --rm frontend_dev npm run lint_fix  # Auto-fix
docker compose run --rm frontend_dev npm run build     # Build static files for production
```

### Dev API
```bash
docker compose run --rm api_dev composer tests
docker compose run --rm api_dev php bin/migrate_databases.php  # Run DB migrations
```

### Startup
```bash
make build && docker compose up   # Full setup
make dev                          # Interactive test shell
```

## Services

| Service | Port | Purpose |
|---------|------|---------|
| `tent_app` | 8080 | Main Tent proxy |
| `api_dev` | 8040 | Mock backend API |
| `frontend_dev` | 8030 | Vite dev server |
| `api_dev_phpmyadmin` | 8050 | DB management UI |
| `tent_httpbin` | 3060 | HTTPBin for testing |

## Architecture

### Request Flow

```
HTTP Request
→ Apache (.htaccess rewrite)
→ source/source/index.php
→ RequestProcessor  (iterates Rule objects, picks first match)
→ Middleware chain  (processRequest)
→ RequestHandler   (proxy / static file / 404)
→ Middleware chain  (processResponse)
→ HTTP Response
```

### Key Components

- **`Configuration`** (`source/source/lib/Configuration.php`): Static rule registry. Rules are defined via `Configuration::buildRule()` in `docker_volumes/configuration/` (not version controlled).
- **`RequestProcessor`** (`lib/service/RequestProcessor.php`): Finds first matching rule and invokes its handler.
- **`Rule`** (`lib/models/Rule.php`): Holds matchers (URI patterns, HTTP methods) and a handler reference.
- **Handlers** (`lib/request_handlers/`): `DefaultProxyRequestHandler` (preferred for proxying), `ProxyRequestHandler`, `StaticFileHandler`, `MissingRequestHandler`.
- **Middlewares** (`lib/middlewares/`): Implement `processRequest(ProcessingRequest)` and/or `processResponse(Response)`. Built-ins: `FileCacheMiddleware`, `SetHeadersMiddleware`, `SetPathMiddleware`, `RenameHeaderMiddleware`.
- **Matchers** (`lib/matchers/`): `ExactRequestMatcher`, `BeginsWithRequestMatcher`, `EndsWithRequestMatcher`, `StatusCodeMatcher`, `ResponseHeaderMatcher`, `RequestMethodMatcher`, `NegativeMatcher`.

### Configuration Rules Pattern

```php
Configuration::buildRule([
  'handler' => [
    'type' => 'default_proxy',  // 'default_proxy' preferred; also 'proxy', 'static'
    'host' => 'http://api:80'
  ],
  'matchers' => [
    ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact'],
    // type: 'exact' or 'begins_with'; method: any HTTP verb
  ],
  'middlewares' => [
    [
      'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
      'location' => './cache',
      'matchers' => [                          // use 'matchers', not deprecated 'httpCodes'
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

#### Deprecated: `httpCodes` on FileCacheMiddleware

```php
// Old (deprecated — triggers deprecation warning in logs)
['class' => 'Tent\\Middlewares\\FileCacheMiddleware', 'location' => './cache', 'httpCodes' => [200]]

// New (use this)
['class' => 'Tent\\Middlewares\\FileCacheMiddleware', 'location' => './cache',
 'matchers' => [['class' => 'Tent\\Matchers\\StatusCodeMatcher', 'httpCodes' => [200]]]]
```

### Class Loading (`loader.php`)

There is **no Composer PSR-4 autoload** for runtime classes. Every new class file must be added explicitly via `require_once __DIR__ . '/lib/...'`:

- Tent app → `source/source/loader.php`
- Dev API → `dev/api/source/loader.php`

Rules: one `require_once` per file, dependency-first ordering (interfaces/base classes before concrete implementations), grouped by domain (`middlewares`, `models`, `matchers`, etc.).

## Dev API (`dev/api/`)

A mock PHP backend used for testing the proxy.

- Entry point: `dev/api/source/index.php` — routes registered with `Configuration::add('METHOD', '/path', EndpointClass::class)`
- Endpoint classes extend `Endpoint` and implement `handle()` returning a `Response`
- To add an endpoint: create the class in `lib/api_dev/endpoints/`, add `require_once` in `loader.php`, register in `index.php`
- DB migrations: numbered `.sql` files in `dev/api/migrations/`, executed in order, re-run each time (use idempotent SQL)

## Dev Frontend (`dev/frontend/`)

React 19 app with Vite, TanStack Query, Bootstrap 5. Tests use Jasmine in `spec/`.

Controlled by `FRONTEND_DEV_MODE` in `.env`:
- `true`: Tent proxies to Vite dev server (port 8030) with HMR — changes reflect immediately
- `false`: Tent serves built static files from `dev/frontend/dist/`

Structure: components in `assets/js/components/`, API clients in `assets/js/clients/`, tests in `spec/`.

## Testing Conventions

- **PHP**: Use `Configuration::reset()` in `setUp()` to clear rules between tests. Test handler behavior via `Rule::match()` and handler execution separately.
- **Frontend**: Jasmine specs in `spec/`, mirroring source structure.

## Key Files

- `source/source/lib/service/RequestProcessor.php` — core routing logic
- `source/source/lib/Configuration.php` — rule registry
- `source/source/lib/request_handlers/RequestHandler.php` — handler base with middleware application
- `source/source/loader.php` — manual class loading (update when adding classes)
- `docker_volumes/configuration/` — user-defined rules (not version controlled)
- `docs/` — implementation guides for middlewares, matchers, handlers

## Language

All code, comments, and documentation must be in **English**.
