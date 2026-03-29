# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What Is Tent

Tent is a PHP-based intelligent reverse proxy server that routes HTTP requests to backend services, caches responses, or serves static files based on configuration rules. It runs on Apache with PHP inside Docker containers.

## Commands

**All commands must use Docker Compose (v2 syntax). Never run commands directly on the host.**

Use `docker compose run` for one-off commands (tests, linting) and `docker compose exec` only when the service is already running with `docker compose up`.

### Backend (PHP)
```bash
docker compose run --rm tent_tests composer tests              # All tests
docker compose run --rm tent_tests composer tests:unit        # Unit tests only
docker compose run --rm tent_tests composer tests:integration # Integration tests
docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/path/to/TestFile.php  # Single test file
docker compose run --rm tent_tests composer lint              # Check code style
docker compose run --rm tent_tests composer lint:fix          # Auto-fix code style
```

### Frontend (React/Vite)
```bash
docker compose run --rm frontend_dev npm test          # Jasmine tests
docker compose run --rm frontend_dev npm run lint      # ESLint
docker compose run --rm frontend_dev npm run lint_fix  # Auto-fix
docker compose run --rm frontend_dev npm run build     # Build static files
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

## Architecture

### Request Flow

```
HTTP Request
→ Apache (.htaccess rewrite)
→ source/source/index.php
→ RequestProcessor (matches request against Rule objects)
→ Middleware chain (processRequest)
→ RequestHandler (proxy / static file / 404)
→ Middleware chain (processResponse)
→ HTTP Response
```

### Key Components

- **`Configuration`** (`source/source/lib/Configuration.php`): Static rule registry. Rules are defined via `Configuration::buildRule()` in `docker_volumes/configuration/` (not version controlled).
- **`RequestProcessor`** (`lib/service/RequestProcessor.php`): Iterates rules, finds the first matching rule, invokes its handler.
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
    // type: 'exact' or 'begins_with'
  ],
  'middlewares' => [
    [
      'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
      'location' => './cache',
      'matchers' => [                                  // use 'matchers' not deprecated 'httpCodes'
        ['class' => 'Tent\\Matchers\\StatusCodeMatcher', 'httpCodes' => [200]]
      ]
    ]
  ]
]);
```

### Class Loading (`loader.php`)

There is **no Composer PSR-4 autoload** for runtime classes. Every new class file must be added explicitly to `source/source/loader.php` (for Tent) or `dev/api/source/loader.php` (for Dev API) via `require_once __DIR__ . '/lib/...'`. Preserve dependency-first ordering (interfaces/base classes before concrete implementations) and keep includes grouped by domain (`middlewares`, `models`, `matchers`, etc.).

### Dev API (`dev/api/`)

A mock PHP backend for testing the proxy. Routes are registered in `dev/api/source/index.php` using `Configuration::add('METHOD', '/path', EndpointClass::class)`. Endpoint classes extend `Endpoint` and implement `handle()`. DB migrations are numbered `.sql` files in `dev/api/migrations/` (re-run each time; use idempotent SQL).

### Dev Frontend (`dev/frontend/`)

React 19 app with Vite, TanStack Query, Bootstrap 5. Tests use Jasmine in `spec/`. Controlled by `FRONTEND_DEV_MODE` in `.env`:
- `true`: Tent proxies to Vite dev server (port 8030) with HMR
- `false`: Tent serves built static files from `dev/frontend/dist/`

### Testing Conventions

- PHP: Use `Configuration::reset()` in `setUp()` to clear rules between tests.
- Frontend: Jasmine specs mirror source structure under `spec/`.

## Services

| Service | Port | Purpose |
|---------|------|---------|
| `tent_app` | 8080 | Main Tent proxy |
| `api_dev` | 8040 | Mock backend API |
| `frontend_dev` | 8030 | Vite dev server |
| `api_dev_phpmyadmin` | 8050 | DB management UI |
| `tent_httpbin` | 3060 | HTTPBin for testing |

## Key Files

- `source/source/lib/service/RequestProcessor.php` — core routing logic
- `source/source/lib/Configuration.php` — rule registry
- `source/source/lib/request_handlers/RequestHandler.php` — handler base with middleware application
- `source/source/loader.php` — manual class loading (update when adding classes)
- `docker_volumes/configuration/` — user-defined rules (not version controlled)
- `docs/` — implementation guides for middlewares, matchers, handlers
