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

**ALWAYS use Docker Compose**. Never run commands directly on the host.

**Important:** Use `docker compose` (v2 syntax) instead of `docker-compose` (v1 syntax).

**Understanding `run` vs `exec`:**

- `docker compose run`: Creates a new container instance to run a one-off command. Use this for:
  - Running tests
  - Installing dependencies
  - One-off commands when containers aren't already running
  - Example: `docker compose run --rm tent_tests composer tests`
  
- `docker compose exec`: Executes a command in an already running container. Use this for:
  - Running commands in active services started with `docker compose up`
  - Interactive debugging in running containers
  - Example: `docker compose exec tent_app composer install` (only if tent_app is already running)

**Recommended Commands:**

```bash
# Backend tests (use run for one-off test execution)
docker compose run --rm tent_tests composer tests

# Frontend tests
docker compose run --rm frontend_dev npm test

# Install dependencies (use run for one-off installation)
docker compose run --rm tent_app composer install
docker compose run --rm frontend_dev npm install

# Linting
docker compose run --rm tent_tests composer lint
docker compose run --rm frontend_dev npm run lint

# Interactive development shell
docker compose run --rm tent_tests /bin/bash

# If services are already running with docker compose up, you can use exec:
docker compose exec tent_app composer install
docker compose exec frontend_dev npm test
```

**Understanding `docker compose run` vs `docker compose exec`:**

- **`run`**: Creates a new container instance, executes the command, then exits. Use for one-off commands or services that aren't continuously running (like `tent_tests`).
- **`exec`**: Executes commands in an already-running container. Use for services that are up and running (like `tent_app`, `frontend_dev`, `api_dev`).

### Development Containers

- `tent_app`: Main Tent proxy (port 8080)
- `tent_tests`: Test environment for backend
- `api_dev`: Mock backend API (port 8040)
- `frontend_dev`: React/Vite dev server (port 8030)
- `api_dev_phpmyadmin`: Database management (port 8050)
- `tent_httpbin`: HTTPBin testing service (port 3060)

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
      'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
      'location' => './cache',
      'matchers' => [
        [
          'class' => 'Tent\\Matchers\\StatusCodeMatcher',
          'httpCodes' => [200]
        ]
      ]
    ],
    [
      'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
      'headers' => [
        'Host' => 'backend.local'
      ]
    ]
  ]
]);
```

### Middleware Pattern

Middlewares implement `processRequest(ProcessingRequest): ProcessingRequest` and/or `processResponse(Response): Response`. They're applied in order and can short-circuit with `$request->setResponse()`.

Examples:

- `FileCacheMiddleware`: Caches responses matching HTTP codes (now configured via `matchers`; `httpCodes` is deprecated)
- `SetHeadersMiddleware`: Overrides request headers
- `SetPathMiddleware`: Changes request path (e.g., `/` → `/index.html`)
## Matcher Configuration Migration

The old `httpCodes` attribute for configuring matchers in middlewares (especially `FileCacheMiddleware`) is deprecated. Use the new `matchers` array for more dynamic and robust configuration:

```php
// Old (deprecated)
[
  'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
  'location' => './cache',
  'httpCodes' => [200]
]

// New (recommended)
[
  'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
  'location' => './cache',
  'matchers' => [
    [
      'class' => 'Tent\\Matchers\\StatusCodeMatcher',
      'httpCodes' => [200]
    ]
  ]
]
```

All documentation and examples should use the new `matchers` pattern. Using `httpCodes` will trigger a deprecation warning in logs.

### Testing Standards

- **PHP**: PHPUnit tests in `source/tests/unit/`. Use `Configuration::reset()` in `setUp()` to clear rules between tests.
- **Frontend**: Jasmine specs in `dev/frontend/spec/`. Run with `npm test`.
- Always test handler behavior via `Rule::match()` and handler execution separately.

### Dev API Application

The `dev/api` directory contains a dummy backend application used for testing the Tent proxy. Key characteristics:

**Architecture:**

- Entry point: `dev/api/source/index.php` receives all requests via Apache rewrite
- Routes are registered in `index.php` using `Configuration::add('METHOD', '/path', EndpointClass::class)`
- `RequestHandler` processes requests and matches them to configured routes
- Endpoint classes extend `Endpoint` and implement `handle()` method returning a `Response`

**Adding New Endpoints:**

1. Create endpoint class in `dev/api/source/lib/api_dev/endpoints/` extending `Endpoint`
2. Implement `handle()` method to return a `Response` with body, status code, and headers
3. Include the endpoint file with `require_once` in `index.php`
4. Register the route: `Configuration::add('GET', '/my-path', MyEndpoint::class)`

**Database Migrations:**

- Migration files: `dev/api/migrations/NNNN_description.sql` (numbered for ordering)
- Run migrations: `docker compose run --rm api_dev php bin/migrate_databases.php`
- Migrations are simple SQL files executed in alphabetical order
- No migration tracking table - migrations re-run each time (use idempotent SQL)

**Testing the Dev API:**
```bash
# Run dev API tests
docker compose run --rm api_dev composer tests

# Access API directly
curl http://localhost:8040/health
curl http://localhost:8040/persons

# Access via Tent proxy (tests proxy behavior)
curl http://localhost:8080/persons
```

See [dev/api/README.md](dev/api/README.md) for comprehensive documentation.

### Dev Frontend Application

The `dev/frontend` directory contains a React-based frontend application used for development and testing of the Tent proxy. Key characteristics:

**Architecture:**

- **Tech Stack**: React 19, Vite (build tool & dev server), Bootstrap 5, TanStack Query (React Query), Jasmine (testing), ESLint
- Entry point: `dev/frontend/index.html` → `assets/js/main.jsx` bootstraps React application
- Components in `assets/js/components/` (e.g., `App.jsx`, `PersonList.jsx`)
- API clients in `assets/js/clients/` (e.g., `PersonClient.js`)
- Tests in `spec/` directory using Jasmine

**Development Mode vs Production Mode:**

Controlled by `FRONTEND_DEV_MODE` environment variable in `.env`:

- **Dev Mode (`true`)**: Tent proxies requests to Vite dev server (port 8030) with Hot Module Reloading (HMR)
  - Changes reflected immediately in browser
  - Access via Tent proxy: <http://localhost:8080>
  - Direct dev server: <http://localhost:8030>

- **Production Mode (`false`)**: Tent serves static files from `dev/frontend/dist/` directory
  - Optimized and minified assets
  - Build with: `docker compose run --rm frontend_dev npm run build`

**Adding New Components:**

1. Create component file in `assets/js/components/`:

```jsx
import React from 'react';

const MyComponent = () => {
  return <div className="my-component">My Component</div>;
};

export default MyComponent;
```

2. Import and use in parent component (e.g., `App.jsx`)

**Adding API Clients:**

1. Create client in `assets/js/clients/`:
2. 
```javascript
const MyClient = {
  async fetchAll() {
    const response = await fetch('/api/resources');
    return await response.json();
  }
};

export default MyClient;
```

2. Use with TanStack Query in components:

```jsx
import { useQuery } from '@tanstack/react-query';
import MyClient from '../clients/MyClient';

const { data, isLoading, error } = useQuery({
  queryKey: ['resources'],
  queryFn: MyClient.fetchAll
});
```

**Testing:**

```bash
# Run Jasmine tests
docker compose run --rm frontend_dev npm test

# Run with coverage
docker compose run --rm frontend_dev npm run coverage
```

Tests follow Jasmine conventions in `spec/` directory:
```javascript
import MyClient from '../../assets/js/clients/MyClient';

describe('MyClient', () => {
  it('should fetch data', async () => {
    const result = await MyClient.fetchAll();
    expect(result).toBeDefined();
  });
});
```

**Linting:**

```bash
# Lint with ESLint
docker compose run --rm frontend_dev npm run lint

# Auto-fix issues
docker compose run --rm frontend_dev npm run lint_fix
```

**Integration with Tent:**

Frontend is integrated via Tent configuration rules in `docker_volumes/configuration/rules/frontend.php`:

- Dev mode: Proxy handler forwards to Vite dev server
- Production mode: Static handler serves from `dist/` directory with `SetPathMiddleware` (e.g., `/` → `/index.html`)

See [dev/frontend/README.md](dev/frontend/README.md) for comprehensive documentation.

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
  │   ├── source/            # API application source
  │   │   ├── index.php      # API entry point
  │   │   └── lib/           # API classes (endpoints, models, DB layer)
  │   ├── migrations/        # SQL migration files
  │   └── bin/               # Utility scripts (database setup, migrations)
  └── frontend/              # React frontend (Vite, Jasmine tests)
      ├── assets/            # Application source code
      │   ├── css/           # Stylesheets (CSS, SCSS)
      │   └── js/            # JavaScript/JSX files
      │       ├── main.jsx   # Application entry point
      │       ├── components/ # React components (App.jsx, PersonList.jsx)
      │       └── clients/   # API client modules (PersonClient.js)
      ├── dist/              # Built static files (production)
      ├── spec/              # Jasmine test files
      ├── index.html         # HTML entry point
      ├── package.json       # Node.js dependencies and scripts
      └── vite.config.js     # Vite configuration
```

## Integration Points

- **Backend API**: Communicates via HTTP proxy through Tent. Tent sets `Host` header via `SetHeadersMiddleware`.
  - The `api_dev` service is a lightweight PHP application that demonstrates typical REST API patterns
  - All requests are routed through `index.php` to a `RequestHandler` that matches routes to endpoint classes
  - See [dev/api/README.md](dev/api/README.md) for details on adding endpoints and running migrations
- **Frontend**: In dev mode (`FRONTEND_DEV_MODE=true`), proxies to Vite server. In prod mode, serves built static files.
  - The `frontend_dev` service is a React 19 application using Vite for development and building
  - Vite dev server runs on port 8030 with Hot Module Reloading (HMR) for instant updates
  - Uses TanStack Query (React Query) for data fetching and state management
  - Components are in `assets/js/components/`, API clients in `assets/js/clients/`
  - Tests use Jasmine framework in `spec/` directory
  - Build command: `docker compose run --rm frontend_dev npm run build` creates optimized static files in `dist/`
  - See [dev/frontend/README.md](dev/frontend/README.md) for details on adding components, API clients, and testing
- **Cache**: `FileCacheMiddleware` stores responses in `docker_volumes/cache/` based on request path hash and HTTP status codes.
- **Database**: `api_dev` connects to MySQL (`api_dev_mysql` container) for mock data.
  - Migrations are stored as numbered `.sql` files in `dev/api/migrations/`
  - Run with: `docker compose run --rm api_dev php bin/migrate_databases.php`

## Key Files to Reference

- [source/source/lib/service/RequestProcessor.php](source/source/lib/service/RequestProcessor.php): Main request routing logic
- [source/source/lib/handlers/RequestHandler.php](source/source/lib/handlers/RequestHandler.php): Handler base class with middleware application
- [source/source/lib/Configuration.php](source/source/lib/Configuration.php): Rule registry and builder
- [docker_volumes/configuration/rules/backend.php](docker_volumes/configuration/rules/backend.php): Example backend proxy rule
- [docker_volumes/configuration/rules/frontend.php](docker_volumes/configuration/rules/frontend.php): Example frontend rules (dev vs prod)
- [dev/api/README.md](dev/api/README.md): Dev API documentation (adding endpoints, migrations)
- [dev/frontend/README.md](dev/frontend/README.md): Dev Frontend documentation (adding components, API clients, testing)

## Language Guidelines

All code, comments, and documentation must be in **English**.
