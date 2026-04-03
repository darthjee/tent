# Dev Application

The `dev/` directory contains a sample full-stack application used to test Tent end-to-end. It exercises Tent's proxy capabilities against a real backend and frontend.

---

## Overview

```
dev/
  api/        ← PHP backend (Apache + MySQL)
  frontend/   ← React 19 + Vite frontend
```

Tent acts as the reverse proxy in front of both: requests to `/persons` (and related routes) are forwarded to `api_dev`, and the frontend is served either via the Vite dev server (`frontend_dev`) or from pre-built static files.

---

## Backend (`dev/api/`)

A self-contained PHP API server that mirrors the same class-loading pattern as Tent (manual `require_once` in `loader.php`, no Composer autoload for runtime classes).

### Entry point

`dev/api/source/index.php` — configures the MySQL connection from environment variables, then registers routes:

| Method | Path                       | Endpoint class                |
|--------|----------------------------|-------------------------------|
| GET    | `/health`                  | `HealthCheckEndpoint`         |
| GET    | `/persons`                 | `ListPersonsEndpoint`         |
| POST   | `/persons`                 | `CreatePersonEndpoint`        |
| POST   | `/persons/:id/photo.json`  | `UploadPersonPhotoEndpoint`   |

Routes are registered with `Configuration::add('METHOD', '/path', EndpointClass::class)`.

### Request lifecycle

1. `Request` is instantiated from the current HTTP request.
2. `RequestHandler` matches the request against registered routes and calls the matched `Endpoint::handle()`.
3. Each endpoint returns a `Response` (body, HTTP status, headers).
4. Unmatched routes return a `MissingResponse` (404).

### Database

MySQL, accessed through `ApiDev\Mysql\Connection`. The `persons` table schema:

```sql
CREATE TABLE persons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    birthdate  DATE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

Migrations live in `dev/api/migrations/` as numbered `.sql` files (e.g. `0001_create_persons.sql`). They are **idempotent** and re-run every time via:

```bash
docker compose run --rm api_dev php bin/migrate_databases.php
```

### Adding an endpoint

1. Create the class in `dev/api/source/lib/api_dev/endpoints/`, extending `Endpoint` and implementing `handle(): Response`.
2. Add `require_once` in `dev/api/source/loader.php`.
3. Register the route in `dev/api/source/index.php`.

---

## Frontend (`dev/frontend/`)

A React 19 SPA built with Vite. It uses TanStack Query for data fetching and Bootstrap 5 for styling.

### Stack

| Tool | Purpose |
|------|---------|
| React 19 + Vite | Component rendering and build tooling |
| TanStack Query | Server state / data fetching |
| Bootstrap 5 | CSS framework |
| Sass | Extended CSS via `.scss` files |

### Structure

```
dev/frontend/
  assets/
    js/
      components/   ← React components (e.g. PersonList.jsx)
      clients/      ← Fetch wrappers (e.g. PersonClient.js)
    css/            ← Styles (.css / .scss)
  spec/             ← Jasmine tests
  index.html        ← App shell
  vite.config.js    ← Vite config
```

Entry point is `assets/js/main.jsx`, which mounts `<App>` inside a `QueryClientProvider`.

### Dev vs production mode

Controlled by `FRONTEND_DEV_MODE` in `.env`:

- `true` — Tent proxies requests to the Vite dev server (`frontend_dev:8080`). Hot Module Replacement works; changes reflect immediately without rebuilding.
- `false` — Tent serves the pre-built static files from `dev/frontend/dist/` (mounted into the Tent container at `source/static/`).

Build static files with:

```bash
docker compose run --rm frontend_dev npm run build
```

---

## Testing

### Backend — PHPUnit

Tests live in `dev/api/tests/unit/`, mirroring the source structure:

```
tests/
  unit/
    lib/
      api_dev/
        endpoints/    ← HealthCheckEndpointTest, ListPersonsEndpointTest, CreatePersonEndpointTest
        models/       ← MissingResponseTest, RequestTest, Person/*
      mysql/          ← ConnectionTest, MigrationTest, ModelConnectionTest, ...
  support/
    database_initializer.php
    models/MockRequest.php
    tests_loader.php
```

Run all dev API tests:

```bash
docker compose run --rm api_dev composer tests
```

Unit tests only (no DB required):

```bash
docker compose run --rm api_dev composer tests:unit
```

### Frontend — Jasmine

Specs live in `dev/frontend/spec/`, mirroring the source structure. The test runner is Jasmine, instrumented with nyc for coverage.

```
spec/
  clients/
    PersonClient/
      PersonClientList_spec.js
      PersonClientCreate_spec.js
  example_spec.js
```

HTTP calls are stubbed with Jasmine spies on `global.fetch` — no real network required.

Run:

```bash
docker compose run --rm frontend_dev npm test
```

---

## CI (CircleCI)

The dev application has four dedicated CI jobs, all running in parallel with the main Tent test jobs:

| Job | What it does |
|-----|-------------|
| `dev_api_test` | Runs PHPUnit against a real MySQL 8.0 container (no mocks). Waits for DB readiness, creates the database, runs migrations, then runs `composer tests`. |
| `dev_api_checks` | Runs `composer lint` (PSR-12 via PHP_CodeSniffer). |
| `dev_frontend_test` | Installs dependencies with Yarn, runs `npm test` (Jasmine). |
| `dev_frontend_checks` | Installs dependencies with Yarn, runs `npm run lint` (ESLint). |

The `build-and-release` job (Docker image push) only runs after **all** test and check jobs pass.

---

## Docker Compose services

| Service | Port | Image | Purpose |
|---------|------|-------|---------|
| `api_dev` | 8040 | `darthjee/dev_tent` | PHP/Apache backend; same base image as Tent. Mounts `dev/api/` as the app root. |
| `frontend_dev` | 8030 | `darthjee/node:0.2.1` | Vite dev server; mounts `dev/frontend/`. |
| `api_dev_mysql` | — | `mysql:9.3.0` | Database for the dev API. Data persisted in `docker_volumes/mysql_data/`. |
| `api_dev_phpmyadmin` | 8050 | `phpmyadmin/phpmyadmin` | Web UI for inspecting the MySQL database. |

### Dependency chain

```
tent_app ──depends on──► api_dev ──depends on──► api_dev_mysql
                      └──depends on──► api_dev_phpmyadmin
         ──depends on──► frontend_dev
tent_tests ──depends on──► api_dev
           ──depends on──► tent_httpbin
```

`tent_tests` links `api_dev` under the hostname `api_dev`, and `tent_app` links it under `api` (used in proxy configuration rules).

### Environment variables (`.env`)

The MySQL connection is configured via environment variables consumed by `api_dev`:

| Variable | Default (index.php fallback) |
|----------|------------------------------|
| `API_DEV_MYSQL_HOST` | `localhost` |
| `API_DEV_MYSQL_DEV_DATABASE` | `api_tent_dev_db` |
| `API_DEV_MYSQL_USER` | `root` |
| `API_DEV_MYSQL_PASSWORD` | _(empty)_ |
| `API_DEV_MYSQL_PORT` | `3306` |
