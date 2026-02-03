# Copilot Instructions for Tent

Tent is a PHP-based intelligent proxy server that routes requests to backend services, caches responses, or serves static files based on configuration rules.

## Architecture Overview

**Request Flow**: Apache rewrites all requests to [source/source/index.php](source/source/index.php) → `RequestProcessor` evaluates configured `Rule` objects → Middlewares process the request → `RequestHandler` executes (proxy/static/cache) → Middlewares process response → Response sent.

**Key Components**:
- **Configuration System**: Rules defined in `docker_volumes/configuration/configure.php` and loaded at runtime
- **Rule Matching**: Each `Rule` has matchers (URI patterns, HTTP methods) and a handler
- **Middleware Chain**: Middlewares modify requests/responses (headers, paths, caching)
- **Handlers**: `ProxyRequestHandler` (forwards to backends), `StaticFileHandler` (serves files), `MissingRequestHandler` (404 fallback)

## Critical Developer Workflows

### Running Commands
**ALWAYS use Docker Compose**. Never run commands directly on the host:
```bash
# Backend tests
docker-compose exec tent_tests composer tests

# Frontend tests
docker-compose exec frontend_dev npm test

# Install dependencies
docker-compose exec tent_app composer install
docker-compose exec frontend_dev npm install

# Linting
docker-compose exec tent_app composer lint
docker-compose exec frontend_dev npm run lint
```

### Development Containers
- `tent_app`: Main Tent proxy (port 8080)
- `tent_tests`: Test environment for backend
- `api_dev`: Mock backend API (port 8040)
- `frontend_dev`: React/Vite dev server (port 8030)
- `api_dev_phpmyadmin`: Database management (port 8050)

### Environment Variable: FRONTEND_DEV_MODE
- `true`: Proxies frontend requests to Vite dev server (hot reload)
- `false`: Serves frontend from static build at `dev/frontend/dist/`

## Project-Specific Conventions

### Configuration Rules Pattern
Rules are defined declaratively using `Configuration::buildRule()`:
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',        // or 'static'
        'host' => 'http://api:80' // for proxy type
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
        // type: 'exact', 'begins_with'
    ],
    'middlewares' => [
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'backend.local']
        ]
    ]
]);
```

### Middleware Pattern
Middlewares implement `processRequest(ProcessingRequest): ProcessingRequest` and/or `processResponse(Response): Response`. They're applied in order and can short-circuit with `$request->setResponse()`.

Examples:

- `FileCacheMiddleware`: Caches responses matching HTTP codes
- `SetHeadersMiddleware`: Overrides request headers
- `SetPathMiddleware`: Changes request path (e.g., `/` → `/index.html`)

### Testing Standards
- **PHP**: PHPUnit tests in `source/tests/unit/`. Use `Configuration::reset()` in `setUp()` to clear rules between tests.
- **Frontend**: Jasmine specs in `dev/frontend/spec/`. Run with `npm test`.
- Always test handler behavior via `Rule::match()` and handler execution separately.

## Directory Structure

```
source/source/               # Core Tent application
  ├── lib/
  │   ├── handlers/          # ProxyRequestHandler, StaticFileHandler, etc.
  │   ├── middlewares/       # Request/response middleware implementations
  │   ├── models/            # Request, Response, Rule, Server, etc.
  │   ├── service/           # RequestProcessor (main routing engine)
  │   └── Configuration.php  # Static rule registry
  └── index.php              # Entry point (processes all HTTP requests)

docker_volumes/configuration/  # User-provided configuration (NOT version controlled)
  ├── configure.php            # Main config loader
  └── rules/                   # Rule definitions (backend.php, frontend.php)

dev/
  ├── api/                   # Mock backend API for development
  └── frontend/              # React frontend (Vite, Jasmine tests)
```

## Integration Points

- **Backend API**: Communicates via HTTP proxy through Tent. Tent sets `Host` header via `SetHeadersMiddleware`.
- **Frontend**: In dev mode (`FRONTEND_DEV_MODE=true`), proxies to Vite server. In prod mode, serves built static files.
- **Cache**: `FileCacheMiddleware` stores responses in `docker_volumes/cache/` based on request path hash and HTTP status codes.
- **Database**: `api_dev` connects to MySQL (`api_dev_mysql` container) for mock data.

## Key Files to Reference

- [source/source/lib/service/RequestProcessor.php](source/source/lib/service/RequestProcessor.php): Main request routing logic
- [source/source/lib/handlers/RequestHandler.php](source/source/lib/handlers/RequestHandler.php): Handler base class with middleware application
- [source/source/lib/Configuration.php](source/source/lib/Configuration.php): Rule registry and builder
- [docker_volumes/configuration/rules/backend.php](docker_volumes/configuration/rules/backend.php): Example backend proxy rule
- [docker_volumes/configuration/rules/frontend.php](docker_volumes/configuration/rules/frontend.php): Example frontend rules (dev vs prod)

## Language Guidelines

All code, comments, and documentation must be in **English**.
