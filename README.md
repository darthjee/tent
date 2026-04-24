# Tent

Tent is an intelligent PHP-based proxy server that can route requests to backend services, serve cached responses, or deliver static files directly - all based on configuration.

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)

![tent](https://raw.githubusercontent.com/darthjee/tent/master/tent.png)

**Current Version:** [0.7.0](https://github.com/darthjee/tent/releases/tag/0.7.0)

**Next Release:** [0.7.1](https://github.com/darthjee/tent/compare/0.7.0...main)

## Documentation

For detailed guides and advanced topics, see our documentation:

- **[How to Use darthjee/tent](docs/HOW_TO_USE_DARTHJEE-TENT.md)** - Complete integration guide: Docker setup, all request handlers, cache configuration, Host header handling, middlewares, and full examples
- **[Creating Middlewares](docs/creating-middlewares.md)** - Learn how to build custom middlewares to process requests and responses
- **[FileCacheMiddleware Matchers](docs/file-cache-middleware-matchers.md)** - Configure response caching with matchers
- **[Full Documentation Index](docs/README.md)** - Browse all available documentation

## Overview

Tent is designed to sit in front of your services and intelligently handle incoming HTTP requests. It can act as a reverse proxy, cache layer, or static file server - making it ideal for optimizing resource usage and improving response times.

## How It Works

Tent uses Apache with PHP to process all incoming requests through a centralized entry point:

1. **Request Routing**: Apache's `.htaccess` rewrites all requests to `index.php`
2. **Request Processing**: The PHP application analyzes the request and configuration
3. **Action Selection**: Based on configuration, Tent will:
    - **Default Proxy Mode**: Forward requests to configured backend servers with default middleware behavior
   - **Static Mode**: Serve static files directly

## Docker Image

Tent is available as a Docker image: `darthjee/tent`

## Current Status

Tent is in active development. Currently implemented:

- ✅ Basic proxy functionality (supports GET, POST, PUT, PATCH, DELETE)
- ✅ Request routing and matching
- ✅ Header forwarding
- ✅ Static file serving (serves files from a directory)
- ✅ Middleware system
- ✅ Response caching
- ❌ Initial Configuration system (planned)
- ❌ File transfer handling (upload/download proxying) is not available yet

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

**Example of api proxying (using `DefaultProxyRequestHandler`):**

```php
use Tent\RequestHandlers\DefaultProxyRequestHandler;

Configuration::buildRule([
    'handler' => [
        'class' => DefaultProxyRequestHandler::class,
        'host'  => 'http://api.com:80',
        // 'cache' => './cache',       // optional, defaults to './cache'
        // 'cacheCodes' => ['2xx'],    // optional, defaults to ['2xx']
        // 'cache' => false,           // set to false to disable caching
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
]);
```

`DefaultProxyRequestHandler` automatically:
1. Renames the incoming `Host` header to `X-Forwarded-Host`.
2. Sets the `Host` header to the configured host value.
3. Caches responses with 2xx status codes (when `cache !== false`).

Use `'type' => 'default_proxy'` for standard proxy rules. Use `'type' => 'proxy'` only when you need a fully custom middleware stack.

**Equivalent custom configuration (`ProxyRequestHandler`):**

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
            'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
            'from'  => 'Host',
            'to'    => 'X-Forwarded-Host'
        ],
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'http://api.com:80'
            ]
        ],
        [
            'class' => 'Tent\Middlewares\FileCacheMiddleware',
            'location' => "./cache",
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
                    'httpCodes' => ["2xx"]
                ]
            ]
        ],
    ]
]);
```

### Built-in Middlewares

- **SetHeadersMiddleware**: Sets or overrides request headers (e.g., Host, X-Test).
- **RenameHeaderMiddleware**: Renames a request header by copying its value to a new name and removing the original.
- **SetPathMiddleware**: Changes the request path, useful for serving a fixed file with StaticFileHandler.
- **FileCacheMiddleware**: Caches responses matching configured HTTP codes (now configured via 'matchers'; 'httpCodes' is deprecated).

All built-in and custom middlewares must extend the `Tent\Middlewares\Middleware` base class (not `RequestMiddleware`).

### Rule Matcher Types

The `type` field in rule `matchers` controls how the URI is matched:

- **`exact`** *(default)*: The request URI must exactly match the configured `uri`.
- **`begins_with`**: The request URI must start with the configured `uri`.
- **`ends_with`**: The request URI must end with the configured `uri`.

Example using `ends_with`:
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
    ],
]);
```

Example with PUT and PATCH (update operations):
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'PUT', 'uri' => '/api/persons/', 'type' => 'begins_with'],
        ['method' => 'PATCH', 'uri' => '/api/persons/', 'type' => 'begins_with'],
    ],
]);
```

Example with DELETE (delete operations):
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'DELETE', 'uri' => '/api/persons/', 'type' => 'begins_with'],
    ],
]);
```

### Built-in Matchers

Matchers can be used in the `matchers` array of `FileCacheMiddleware` (and other middlewares) to control when caching or other behavior applies.

- **StatusCodeMatcher**: Matches responses by HTTP status code. Supports exact codes (e.g., `200`) and wildcard patterns (e.g., `"2xx"`).
- **RequestMethodMatcher**: Matches requests by HTTP method (e.g., `GET`, `POST`).
- **ResponseHeaderMatcher**: Matches responses that contain any of the specified header name+value pairs. Header names are case-insensitive; values are case-sensitive.

  Example usage with `FileCacheMiddleware`:
  ```php
  [
      'class' => 'Tent\Middlewares\FileCacheMiddleware',
      'location' => './cache',
      'matchers' => [
          [
              'class' => 'Tent\Matchers\ResponseHeaderMatcher',
              'headers' => ['X-SaveCache' => 'true', 'X-Cache-This' => 'some_other_value']
          ]
      ]
  ]
  ```
  If the response contains **any** of the specified header+value pairs (e.g., `X-SaveCache: true` OR `X-Cache-This: some_other_value`), the matcher returns true.

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

### Setup and Running

1. **Clone the repository:**
   ```bash
   git clone https://github.com/darthjee/tent.git
   cd tent
   ```

2. **Configure environment variables:**

   A sample `.env` file is included with default values. Modify if needed, especially `FRONTEND_DEV_MODE`:
   - `FRONTEND_DEV_MODE=true`: Proxies frontend requests to Vite dev server (hot reload)
   - `FRONTEND_DEV_MODE=false`: Serves frontend from static build at `dev/frontend/dist/`
   - `LOG_LEVEL`: Controls Tent log verbosity. Accepted values: `debug` (default), `info`, `warn`, `error`. Only messages at or above the configured level are written to stdout.

3. **Build and start:**
   ```bash
   # Build and start all services
   make build && docker compose up

   # Or use separate commands
   docker compose build base_build
   docker compose up
   ```

### Accessing Services

- **Tent Proxy**: <http://localhost:8080> - Main application entry point
- **Backend API**: <http://localhost:8040> - Development backend service
- **Frontend Dev Server**: <http://localhost:8030> - Vite development server (when `FRONTEND_DEV_MODE=true`)
- **phpMyAdmin**: <http://localhost:8050> - Database management interface
- **HTTPBin**: <http://localhost:3060> - Testing service

### Testing and Development

**Running Tests:**
```bash
# Tent proxy (PHP) tests
docker compose run --rm tent_tests composer tests           # All tests
docker compose run --rm tent_tests composer tests:unit      # Unit tests only
docker compose run --rm tent_tests composer tests:integration  # Integration tests only

# Frontend tests (for dev frontend app)
docker compose run --rm frontend_dev npm test
```

**Linting:**
```bash
# PHP code
docker compose run --rm tent_tests composer lint
docker compose run --rm tent_tests composer lint:fix

# Frontend code
docker compose run --rm frontend_dev npm run lint
docker compose run --rm frontend_dev npm run lint_fix
```

**Development Commands:**
```bash
# Make shortcuts
make dev      # Interactive shell in test environment
make dev-up   # Start tent_app service with dependencies
make tests    # Interactive shell in test environment

# Docker Compose
docker compose up -d              # Start services in detached mode
docker compose logs -f tent_app   # View logs
docker compose down               # Stop all services
docker compose build tent_app     # Rebuild specific service
```

For more details on auxiliary services:

- Backend API development: See [Dev API README](dev/api/README.md)
- Frontend development: See [Dev Frontend README](dev/frontend/README.md)

## Development

The Tent development environment includes the main proxy application plus three auxiliary services for testing:

- **Backend (api_dev):** A simple PHP backend with sample endpoints. See [Dev API README](dev/api/README.md) for details.
- **Frontend (frontend_dev):** A React application served by Vite. See [Dev Frontend README](dev/frontend/README.md) for details.
- **phpMyAdmin (api_dev_phpmyadmin):** For managing the backend database.

### Request Routing

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

Backend requests are proxied to `api_dev`. Frontend requests route based on `FRONTEND_DEV_MODE`:

- `true`: Proxies to Vite dev server (hot reload enabled)
- `false`: Serves static files from `frontend/dist` (production mode)

### Docker Volumes

Key volume mounts for development:

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

See `docker-compose.yml` for complete service configuration.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

## License

See [LICENSE](LICENSE) file for details.
