
# darthjee/tent Docker Image

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

![tent](https://raw.githubusercontent.com/darthjee/tent/master/tent.png)

An intelligent PHP-based proxy server for routing, static file serving, and middleware—fully configurable via PHP files.

## Quick Start

To run a proxy server using this image:

```yaml
services:
  tent:
    image: darthjee/tent:latest
    ports:
      - "8080:80"
    volumes:
      - ./your-config:/var/www/html/configuration/           # REQUIRED: your PHP configuration files
      - ./your-static:/var/www/html/static/                  # OPTIONAL: static files (if your config uses static/fixed handlers)
    env_file:
      - .env                                                # OPTIONAL: environment variables
```

- **/var/www/html/configuration/** (required): Mount your PHP configuration files here. These files define proxy rules, static file handling, and middleware. See below for configuration examples.
- **/var/www/html/static/** (optional): Mount static files here if your configuration serves static content.

## Configuration

Configuration is done via PHP files placed in `/var/www/html/configuration/`. Each file can define one or more rules using the `Tent\\Configuration::buildRule` method. Example files:

- `configure.php`: Main entry, includes other rule files.
- `rules/frontend.php`: Rules for frontend/static/proxy handling.
- `rules/backend.php`: Rules for backend proxying.

### Example: Minimal configure.php

```php
<?php
require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
```

#### Example: Proxy rule (backend.php)

```php
<?php
use Tent\Configuration;
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

#### Example: Frontend rule (frontend.php)

```php
<?php
use Tent\Configuration;

if (getenv('FRONTEND_DEV_MODE') === 'true') {
  Configuration::buildRule([
    'handler' => [
      'type' => 'proxy',
      'host' => 'http://frontend:8080'
    ],
    'matchers' => [
      ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
    ],
    'middlewares' => [
      [
        'class' => 'Tent\Middlewares\SetHeadersMiddleware',
        'headers' => ['Host' => 'frontend.local']
      ]
    ]
  ]);
} else {
  Configuration::buildRule([
    'handler' => [
      'type' => 'static',
      'location' => '/var/www/html/static'
    ],
    'matchers' => [
      ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
    ],
    'middlewares' => [
      [
        'class' => 'Tent\Middlewares\SetPathMiddleware',
        'path' => '/index.html'
      ]
    ]
  ]);
}
```

## Exposed Port

- **80**: The HTTP server listens on port 80 inside the container. Map this to your desired host port (e.g., `8080:80`).

## Typical Use Cases

- Reverse proxy for backend APIs
- Serving static frontend files
- Middleware for custom request/response handling
- Flexible routing based on PHP configuration

## Example Compose Service

```yaml
services:
  tent:
    image: darthjee/tent:latest
    ports:
      - "8080:80"
    volumes:
      - ./docker_volumes/configuration:/var/www/html/configuration/
      - ./dev/frontend/dist:/var/www/html/static/
    env_file:
      - .env
```

## More Information

- See the [README](https://github.com/darthjee/tent) for development instructions and advanced configuration.
- Issues and contributions are welcome!


## How It Works

Tent uses Apache with PHP to process all incoming requests through a centralized entry point:

1. **Request Routing**: Apache's `.htaccess` rewrites all requests to `index.php`
2. **Request Processing**: The PHP application analyzes the request and configuration
3. **Action Selection**: Based on configuration, Tent will:
   - **Proxy Mode**: Forward requests to configured backend servers
   - **Static Mode**: Serve static files directly

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

Tent includes a flexible middleware system for request/response processing. Built-in middlewares include:

- **FileCacheMiddleware**: Caches responses to files based on HTTP status codes
- **SetHeadersMiddleware**: Sets or overrides request headers
- **SetPathMiddleware**: Changes the request path (useful for serving index.html on root requests)

Middlewares are specified in configuration rules and executed in order. See configuration examples below.
