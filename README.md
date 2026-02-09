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

Before you begin, ensure you have the following installed:
- **Docker** (version 20.10 or higher)
- **Docker Compose** (version 2.0 or higher)
- **Git** (for cloning the repository)

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/darthjee/tent.git
   cd tent
   ```

2. **Verify the .env file**
   
   The repository includes a `.env` file with default configuration for development. Review and modify if needed:
   ```bash
   cat .env
   ```

3. **Build the Docker images**
   
   Before running the services for the first time, you need to build the Docker images:
   ```bash
   docker compose build base_build
   ```
   
   This command will:
   - Pull the required base images from Docker Hub
   - Build the custom Tent development image
   - Install PHP dependencies via Composer
   - Set up the development environment

   **Note:** The first build may take several minutes (10-15 minutes) as it downloads and installs all dependencies. Composer needs to download numerous PHP packages, and you may see warnings about GitHub API rate limits during the build process. This is normal and the build will complete successfully.
   
   **Alternative:** If you prefer not to build locally, you can pull the pre-built images directly:
   ```bash
   docker compose pull
   ```

### Running the Development Environment

Once the images are built, you can start the services:

```bash
# Start all services
docker compose up

# Or start services in detached mode (background)
docker compose up -d

# Or use the Makefile shortcut
make dev-up
```

### Accessing the Services

Once the services are running, you can access them at the following URLs:

- **Tent Proxy:** http://localhost:8080 (Main application entry point)
- **Backend API:** http://localhost:8040 (Development backend with /persons endpoint)
- **Frontend:** http://localhost:8030 (React/Vite dev server)
- **phpMyAdmin:** http://localhost:8050 (Database management interface)
- **HTTPBin:** http://localhost:3060 (HTTP testing service)

### Running Tests

To run the PHP backend tests:

```bash
# Run all tests
docker compose run --rm tent_tests composer tests

# Run only unit tests
docker compose run --rm tent_tests composer tests:unit

# Run only integration tests
docker compose run --rm tent_tests composer tests:integration

# Or use the Makefile to get an interactive shell in the test container
make tests
# Then inside the container:
composer tests
```

To run the frontend tests:

```bash
docker compose run --rm frontend_dev npm test
```

### Running Linters

To check code style:

```bash
# PHP linting
docker compose run --rm tent_tests composer lint

# PHP linting with auto-fix
docker compose run --rm tent_tests composer lint:fix

# Frontend linting
docker compose run --rm frontend_dev npm run lint
```

### Managing Dependencies

To install or update dependencies:

```bash
# Install PHP dependencies
docker compose run --rm tent_app composer install

# Update PHP dependencies
docker compose run --rm tent_app composer update

# Install frontend dependencies
docker compose run --rm frontend_dev npm install
```

### Interactive Development Shell

To get an interactive shell in any container:

```bash
# Tent test environment (includes all development tools)
make dev
# or
docker compose run --rm tent_tests /bin/bash

# Backend API container
make dev-api
# or
docker compose run --rm api_dev /bin/bash
```

### Stopping the Services

```bash
# Stop all running services
docker compose down

# Stop and remove volumes (clears database data)
docker compose down -v
```

### Troubleshooting

**Issue: "pull access denied for darthjee/dev_tent"**

If you encounter an error about the Docker image not being available when building, this means the base image needs to be pulled or built locally. The project uses pre-built base images from Docker Hub (`darthjee/dev_tent-base:0.0.1`). 

Solution:
```bash
# Pull the base image first
docker pull darthjee/dev_tent-base:0.0.1

# Then build the development image
docker compose build base_build
```

**Issue: Build taking too long or timing out**

The composer install step during build can take 10-15 minutes due to:
- Downloading many PHP packages
- GitHub API rate limiting during package downloads

This is normal. Let the build complete - you'll see progress messages about syncing packages. Once complete, subsequent builds will be much faster due to Docker layer caching.

**Issue: "command not found: docker-compose"**

If you see this error, you're using Docker Compose v1 syntax. Update your commands to use `docker compose` (with a space) instead of `docker-compose` (with a hyphen).

**Issue: Containers fail to start or have permission issues**

Ensure that the required directories exist and have proper permissions:
```bash
mkdir -p docker_volumes/vendor docker_volumes/cache docker_volumes/node_modules docker_volumes/mysql_data
```

**Issue: Port already in use**

If you get a "port is already allocated" error, another service is using one of the required ports. You can either:
- Stop the conflicting service
- Modify the port mappings in `docker-compose.yml`

**Issue: Frontend not loading or showing errors**

Check the `FRONTEND_DEV_MODE` environment variable in your `.env` file:
- Set to `true` to use the Vite development server (with hot reload)
- Set to `false` to serve static files (requires building the frontend first)

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
