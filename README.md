# Tent

Tent is an intelligent PHP-based proxy server that can route requests to backend services, serve cached responses, or deliver static files directly - all based on configuration.

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

![tent](https://raw.githubusercontent.com/darthjee/tent/master/tent.png)

**Current Version:** [0.3.0](https://github.com/darthjee/tent/releases/tag/0.3.0)

**Next Release:** [0.3.1](https://github.com/darthjee/tent/compare/0.3.0...main)

## Overview

Tent is designed to sit in front of your services and intelligently handle incoming HTTP requests. It can act as a reverse proxy, cache layer, or static file server - making it ideal for optimizing resource usage and improving response times.

## How It Works

Tent uses Apache with PHP to process all incoming requests through a centralized entry point:

1. **Request Routing**: Apache's `.htaccess` rewrites all requests to `index.php`
2. **Request Processing**: The PHP application analyzes the request and configuration
3. **Action Selection**: Based on configuration, Tent will:
   - **Proxy Mode**: Forward requests to configured backend servers
   - **Static Mode**: Serve static files directly (future feature)

## Docker Image

Tent is available as a Docker image: `darthjee/tent`

## Current Status

Tent is in active development. Currently implemented:

- ✅ Basic proxy functionality
- ✅ Request routing and matching
- ✅ Header forwarding
- ✅ Static file serving (serves files from a directory)
- ✅ Middleware system (ready)
- ⏳ Initial Configuration system (in progress)
- ✅ Response caching

### Error Responses (403/404)

Currently, 404 (Not Found) and 403 (Forbidden) responses return a simple default body. In the future, Tent will support custom bodies or templates for these responses, allowing more complex or branded error pages.

In the future, custom body will be available through configuration

## Architecture

```
Client Request
   ↓
   Apache (.htaccess rewrite)
   ↓
   index.php
   ↓
RequestProcessor
   ↓
┌─────────────────────────────┐
│      Middleware Chain       │
│ ┌─────────────────────────┐ │
│ │ FileCacheMiddleware     │ │
│ │ SetHeadersMiddleware    │ │
│ │ CacheMiddleware         │ │
│ │ ...                     │ │
│ └─────────────────────────┘ │
└─────────────────────────────┘
   ↓
Handler Selection
 ┌────────────┬───────────────┬─────────────┐
 ↓            ↓               ↓
Proxy     StaticFile      Error
Handler   Handler         Handler
                  ┌─────────────┐
                  ↓             ↓
               404 Not Found   403 Forbidden
```

## Middleware System

Tent supports a flexible middleware system that allows you to intercept, modify, or enrich requests before they reach the final handler. Middlewares are defined in the configuration and are executed in the order they appear, forming a processing chain.

### How to Use

Middlewares are specified in the `middlewares` array of a rule in your configuration. Each middleware can modify aspects of the request, such as headers or path, or perform custom logic.

**Example of static files serving:**

```php
Configuration::buildRule([
   'handler' => [
      'type' => 'static',
      'location' => '/var/www/html/static/'
   ],
   'matchers' => [
      ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
   ],
   'middlewares' => [
      [
         'class' => 'Tent\\Middlewares\\SetPathMiddleware',
         'path' => '/index.html'
      ],
      [
         'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
         'headers' => [
            'Host' => 'frontend.local'
         ]
      ]
   ]
]);
```

**Example of api proxing:**

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api.com:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    "middlewares" => [
        [
            'class' => 'Tent\Middlewares\FileCacheMiddleware',
            'location' => "./cache",
            'httpCodes' => ["2xx"]
        ],
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'api.com'
            ]
        ]
    ]
]);
```

### Built-in Middlewares

- **SetHeadersMiddleware**: Sets or overrides request headers (e.g., Host, X-Test).
- **SetPathMiddleware**: Changes the request path, useful for serving a fixed file with StaticFileHandler.
- **FileCacheMiddleware**: Caches responses matching configured HTTP codes.

All built-in and custom middlewares must extend the `Tent\Middlewares\Middleware` base class (not `RequestMiddleware`).

#### Implementing a Custom Middleware

To create your own middleware, extend the `Middleware` base class and override `processRequest()` and/or `processResponse()` as needed:

```php
namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

class MyCustomMiddleware extends Middleware
{
   public function processRequest(ProcessingRequest $request): ProcessingRequest
   {
      // Custom request logic
      return $request;
   }

   public function processResponse(Response $response): Response
   {
      // Custom response logic
      return $response;
   }
}
```

To use your middleware, reference its class and any required parameters in your configuration rule (see examples above).

Middlewares make Tent highly customizable, enabling advanced routing, header manipulation, authentication, caching, and more.

## Getting Started

### Prerequisites

- Docker (version 20.10 or higher)
- Docker Compose (v2.0 or higher)
- Git

### Initial Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/darthjee/tent.git
   cd tent
   ```

2. **Create/verify the .env file:**
   
   A sample `.env` file is included in the repository with default values:
   ```
   API_DEV_MYSQL_HOST=mysql
   API_DEV_MYSQL_USER=root
   API_DEV_MYSQL_PASSWORD=tent
   API_DEV_MYSQL_PORT=3306
   API_DEV_MYSQL_TEST_DATABASE=api_tent_test_db
   API_DEV_MYSQL_DEV_DATABASE=api_tent_dev_db
   
   FRONTEND_DEV_MODE=false
   ```
   
   You can modify this file if needed, especially `FRONTEND_DEV_MODE` (see below).

3. **Build the Docker images:**
   
   You can use Make commands to build the images:
   ```bash
   # Build a fresh new image
   make build
   
   # Or ensure the image exists (builds only if needed)
   make ensure-image
   ```
   
   Alternatively, you can use Docker Compose directly:
   ```bash
   docker compose build base_build
   ```

4. **Start the development environment:**
   ```bash
   # Start all services
   docker compose up
   
   # Or use the Make target to start just the main Tent app with dependencies
   make dev-up
   ```

### Accessing Services

Once the services are running, you can access:

- **Tent Proxy**: <http://localhost:8080> - Main application entry point
- **Backend API**: <http://localhost:8040> - Development backend service
- **Frontend Dev Server**: <http://localhost:8030> - Vite development server (when `FRONTEND_DEV_MODE=true`)
- **phpMyAdmin**: <http://localhost:8050> - Database management interface
- **HTTPBin**: <http://localhost:3060> - Testing service

### Running Tests

**Proxy Backend (PHP) Tests:**

These tests validate the Tent proxy application itself. Note that there is also a Dev Backend application (auxiliary service used for testing the proxy) that can be tested separately.
```bash
# Run all PHP tests
docker compose run tent_tests composer tests

# Run only unit tests
docker compose run tent_tests composer tests:unit

# Run only integration tests
docker compose run tent_tests composer tests:integration

# Interactive shell in test environment
docker compose run tent_tests /bin/bash
```

**Dev Frontend Tests:**

These are tests for the development frontend application (an auxiliary React application used to test the proxy). In the future, the proxy will have its own frontend for configuration.
```bash
# Run frontend tests (requires frontend_dev container to be running)
docker compose exec frontend_dev npm test

# Or start a shell in the frontend container
docker compose run frontend_dev /bin/bash
```

**Linting:**
```bash
# Lint PHP code
docker compose run tent_tests composer lint

# Fix PHP code style issues
docker compose run tent_tests composer lint:fix

# Lint frontend code
docker compose exec frontend_dev npm run lint

# Fix frontend linting issues
docker compose exec frontend_dev npm run lint_fix
```

### Development Commands

**Using Make:**
```bash
make dev      # Start interactive shell in test environment
make dev-up   # Start the tent_app service with all dependencies
make tests    # Start interactive shell in test environment (same as dev)
```

**Using Docker Compose directly:**
```bash
# Start services in detached mode
docker compose up -d

# View logs
docker compose logs -f tent_app

# Stop all services
docker compose down

# Rebuild specific service
docker compose build tent_app
```

## Development

To develop Tent, you will run the main Tent application (in the source/source directory) along with three auxiliary services:

- **Backend (api_dev):** A simple PHP backend with endpoints (currently /persons).
- **Frontend (frontend_dev):** A React frontend, served by Vite in development mode.
- **phpMyAdmin (api_dev_phpmyadmin):** For managing and inserting data into the backend database.

### How requests are routed

Tent is configured so that backend requests are proxied to the backend service. Frontend requests depend on the `FRONTEND_DEV_MODE` environment variable:

- If `FRONTEND_DEV_MODE=true`, frontend requests are proxied to the Vite development server (hot reload, etc).
- If `FRONTEND_DEV_MODE=false`, the frontend is served statically from the built files (as in production).

```
Browser
   ↓
Tent (index.php)
   ↓
 ┌───────────────┬────────────────────┐
 │               │                    │
Backend       Frontend (React)   Static Files
 (api_dev)    (frontend_dev)     (frontend/dist)
   ↑               ↑                    ↑
   │               │                    │
phpMyAdmin   (Vite dev server)   (Served by Tent)
```

Depending on FRONTEND_DEV_MODE:

- If true: frontend requests → Vite dev server (hot reload)
- If false: frontend requests → static files from build

### Docker Volumes

- **Static files:** The static files are mounted from `frontend/dist` into the Tent container, so the built frontend is served in production mode.
- **Configuration:** The `docker_volumes/configuration` directory is mounted into the Tent app for configuration. The shipped code does not include a configuration; users are expected to provide their own to define proxy rules.

```
Host Directory                →   Container Path
----------------------------------------------------------
./source                      →   /home/app/app
./dev/frontend/dist           →   /home/app/app/source/static
./docker_volumes/vendor       →   /home/app/app/vendor
./docker_volumes/configuration→   /home/app/app/source/configuration
./dev/api                     →   /home/app/app (for api_dev)
./docker_volumes/mysql_data   →   /var/lib/mysql (for api_dev_mysql)
./docker_volumes/node_modules →   /home/node/app/node_modules (for frontend_dev)
```

See `docker-compose.yml` for details on service setup and volume mounts.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

## License

See [LICENSE](LICENSE) file for details.
