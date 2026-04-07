# How to Use darthjee/tent

[Tent](https://github.com/darthjee/tent) is a PHP-based reverse proxy and static file server distributed as a Docker image. It acts as the single entry point for applications that combine a backend API and a frontend — routing, caching, and serving files through a simple PHP configuration layer.

---

## Table of Contents

- [Quick Start with Docker](#quick-start-with-docker)
- [Configuration Folder Layout](#configuration-folder-layout)
- [Defining Rules](#defining-rules)
- [Request Handlers](#request-handlers)
  - [default_proxy — recommended proxy handler](#default_proxy--recommended-proxy-handler)
  - [proxy — low-level proxy handler](#proxy--low-level-proxy-handler)
  - [static — serve files from disk](#static--serve-files-from-disk)
  - [Which handler should I use?](#which-handler-should-i-use)
- [Host Header and Why It Matters](#host-header-and-why-it-matters)
- [Middlewares](#middlewares)
  - [FileCacheMiddleware](#filecachemiddleware)
  - [SetHeadersMiddleware](#setheadersmiddleware)
  - [RenameHeaderMiddleware](#renameheadermiddleware)
  - [SetPathMiddleware](#setpathmiddleware)
- [Cache Configuration](#cache-configuration)
  - [Cache enabled (default)](#cache-enabled-default)
  - [Cache disabled](#cache-disabled)
  - [Custom cache location and codes](#custom-cache-location-and-codes)
  - [Manual FileCacheMiddleware setup](#manual-filecachemiddleware-setup)
- [Frontend Dev Mode Flip](#frontend-dev-mode-flip)
- [Static Files](#static-files)
- [Complete Example Layout](#complete-example-layout)
- [Reference](#reference)

---

## Quick Start with Docker

Pull the image and run it:

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    ports:
      - "0.0.0.0:80:80"
    volumes:
      - ./proxy/static/:/var/www/html/static/
      - ./proxy_configuration/:/var/www/html/configuration/
    links:
      - my_backend:backend
      - my_frontend:frontend
    env_file: .env
```

The two key mounts are:
- `/var/www/html/static/` — static files Tent will serve directly.
- `/var/www/html/configuration/` — PHP rule files that define routing behavior.

---

## Configuration Folder Layout

Tent reads from `/var/www/html/configuration/` inside the container. The expected entry point is `configure.php`. A typical layout:

```
proxy_configuration/
├── configure.php          # entry point — loads rule files
└── rules/
    ├── backend.php        # routing rules for the API
    └── frontend.php       # routing rules for the frontend
```

### `configure.php`

This is the file Tent boots from. Its only job is to include the rule files:

```php
<?php

use Tent\Configuration;

require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
```

You can split rules into as many files as makes sense for your project — the only requirement is that `configure.php` requires them all.

---

## Defining Rules

Each rule is registered with `Configuration::buildRule()`. A rule has three parts:

- **`handler`** — what to do with the request (proxy it, serve a file, serve a folder).
- **`matchers`** — which requests this rule applies to.
- **`middlewares`** (optional) — transformations applied before or after the handler.

### Matcher types

| `type`        | Behavior                                          |
|---------------|---------------------------------------------------|
| `exact`       | Matches only if the URI is exactly equal          |
| `begins_with` | Matches if the URI starts with the given prefix   |

Matchers also accept a `method` field (`GET`, `POST`, `PUT`, `DELETE`, etc.). When `method` is omitted, the rule matches any HTTP method for the given URI pattern.

---

## Request Handlers

### `default_proxy` — recommended proxy handler

`DefaultProxyRequestHandler` is the recommended handler for proxying requests to a backend. It automatically adds:

1. `RenameHeaderMiddleware('Host', 'X-Forwarded-Host')` — preserves the original `Host` header.
2. `SetHeadersMiddleware(['Host' => <configured host>])` — sets the correct `Host` for the upstream.
3. `FileCacheMiddleware` — caches successful responses to disk (unless disabled).

This means you get correct Host header handling and caching out of the box, with no extra configuration.

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://backend:8080'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

#### Options

| Option       | Type             | Required | Default     | Description |
|--------------|------------------|----------|-------------|-------------|
| `host`       | `string`         | Yes      | —           | Upstream backend URL |
| `cache`      | `string\|false`  | No       | `'./cache'` | Cache directory path, or `false` to disable |
| `cacheCodes` | `array`          | No       | `['2xx']`   | HTTP status codes/patterns to cache |

---

### `proxy` — low-level proxy handler

`ProxyRequestHandler` forwards the request as-is. It adds **no** default middlewares — no Host header rewriting, no caching. Use it when you need full control over the middleware stack.

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://backend:8080'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
            'from'  => 'Host',
            'to'    => 'X-Forwarded-Host'
        ],
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'backend']
        ]
    ]
]);
```

---

### `static` — serve files from disk

`StaticFileHandler` serves files from a local directory. Tent maps the URI path to a file path inside `location`. A request for `/assets/js/app.js` will serve `/var/www/html/static/assets/js/app.js`.

Returns `403 Forbidden` for path traversal attempts and `404 Not Found` for missing files.

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'static',
        'location' => '/var/www/html/static'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/assets/', 'type' => 'begins_with']
    ]
]);
```

| Option     | Type     | Required | Description |
|------------|----------|----------|-------------|
| `location` | `string` | Yes      | Base directory for static files |

---

### Which handler should I use?

| Scenario | Handler |
|----------|---------|
| Proxying to a backend API (standard case) | `default_proxy` |
| Proxying to a dev server (e.g. Vite HMR) without cache | `proxy` |
| Serving pre-built JS/CSS/images from disk | `static` |
| Full custom middleware stack | `proxy` |

In almost all backend proxy scenarios, prefer `default_proxy`. Only drop down to `proxy` when you explicitly need to change or omit the default middleware behavior.

---

## Host Header and Why It Matters

When Tent forwards a request to an upstream backend, the `Host` header it sends determines how the backend identifies the virtual host being requested. Getting this wrong is a common source of routing failures with frameworks like Rails, Django, Express, and PHP servers such as Apache or nginx.

**The problem**: By default, the `Host` header in the forwarded request still contains the hostname the browser sent (e.g., `localhost` or `myapp.com`). Many backends reject or misroute requests where `Host` does not match the expected service name.

**The solution**: Override the `Host` header to match the upstream service's hostname, and preserve the original value under `X-Forwarded-Host`.

`default_proxy` handles this automatically:
- The original `Host` is renamed to `X-Forwarded-Host`.
- `Host` is set to the hostname part of the configured `host` URL.

If you use `proxy` directly, you must do this manually:

```php
'middlewares' => [
    // 1. Preserve original Host for the backend to inspect if needed
    [
        'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
        'from'  => 'Host',
        'to'    => 'X-Forwarded-Host'
    ],
    // 2. Set the Host the upstream expects
    [
        'class' => 'Tent\Middlewares\SetHeadersMiddleware',
        'headers' => [
            'Host' => 'backend'
        ]
    ]
]
```

### Example: backend on a named Docker service

```yaml
# docker-compose.yml
services:
  proxy:
    image: darthjee/tent:latest
    links:
      - my_api:api
  my_api:
    image: myapp/api:latest
```

```php
// rules/backend.php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:3000'
        // Host header will be set to 'api' automatically
    ],
    'matchers' => [
        ['uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

---

## Middlewares

Middlewares sit between the incoming request and the handler. Each middleware can:

- **Modify the request** before it reaches the handler (change headers, rewrite paths, serve from cache).
- **Modify the response** before it is sent to the client (add headers, cache to disk).

Middlewares are applied in the order they appear in the configuration array.

---

### `FileCacheMiddleware`

Caches upstream responses to disk and serves them on subsequent identical requests, bypassing the backend entirely.

```php
[
    'class' => 'Tent\Middlewares\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\Matchers\StatusCodeMatcher',
            'httpCodes' => ['2xx']
        ]
    ]
]
```

#### Cache matchers

Matchers inside `FileCacheMiddleware` control which responses are stored. **All** matchers must pass for a response to be cached (logical AND).

**`StatusCodeMatcher`** — match by HTTP status code:

```php
// Cache exact codes
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => [200, 301]]

// Cache any 2xx response
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => ['2xx']]

// Cache 2xx and redirects
['class' => 'Tent\Matchers\StatusCodeMatcher', 'httpCodes' => ['2xx', 301, 302]]
```

**`RequestMethodMatcher`** — match by HTTP method:

```php
// Only cache GET and HEAD requests
['class' => 'Tent\Matchers\RequestMethodMatcher', 'requestMethods' => ['GET', 'HEAD']]
```

#### Cache file structure

Cache files are named from a hash of the request path. Each unique URI maps to a unique cache file. The `location` directory is created automatically if it does not exist.

> **Note**: There is no built-in cache expiry. To clear the cache, delete the files in the `location` directory.

---

### `SetHeadersMiddleware`

Injects or overrides request headers before the request is forwarded to the backend.

```php
[
    'class' => 'Tent\Middlewares\SetHeadersMiddleware',
    'headers' => [
        'Host'            => 'backend.internal',
        'X-Custom-Header' => 'value'
    ]
]
```

Common uses: setting `Host`, injecting auth tokens, adding routing headers.

---

### `RenameHeaderMiddleware`

Copies the value of one request header to a new name and removes the original.

```php
[
    'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
    'from'  => 'Host',
    'to'    => 'X-Forwarded-Host'
]
```

This is typically paired with `SetHeadersMiddleware`: first preserve the original header, then overwrite it with the correct upstream value. `default_proxy` does this pair automatically.

---

### `SetPathMiddleware`

Rewrites the request path before it reaches the handler.

```php
[
    'class' => 'Tent\Middlewares\SetPathMiddleware',
    'path' => '/index.html'
]
```

Primarily used with `StaticFileHandler` to map `/` to `/index.html` for single-page applications.

---

## Cache Configuration

### Cache enabled (default)

When using `default_proxy`, cache is enabled by default at `./cache` and covers any `2xx` response:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:3000'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

### Cache disabled

Pass `'cache' => false` to skip caching entirely. Use this for write endpoints, authenticated responses, or any endpoint that must not be cached:

```php
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => false
    ],
    'matchers' => [
        ['uri' => '/api/users', 'type' => 'begins_with']
    ]
]);
```

### Custom cache location and codes

Use a dedicated cache directory per service and restrict which codes are stored:

```php
Configuration::buildRule([
    'handler' => [
        'type'       => 'default_proxy',
        'host'       => 'http://api:3000',
        'cache'      => './cache/api',
        'cacheCodes' => [200, 301]
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

### Manual `FileCacheMiddleware` setup

When using `proxy` instead of `default_proxy`, configure `FileCacheMiddleware` explicitly. Place it **before** header middlewares so cached responses are served without forwarding:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:3000'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    'middlewares' => [
        // Cache first — short-circuits on hit, skipping the backend entirely
        [
            'class'    => 'Tent\Middlewares\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class'     => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => ['2xx']
                ],
                [
                    'class'          => 'Tent\Matchers\RequestMethodMatcher',
                    'requestMethods' => ['GET']
                ]
            ]
        ],
        // Then fix Host header for the backend
        [
            'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
            'from'  => 'Host',
            'to'    => 'X-Forwarded-Host'
        ],
        [
            'class'   => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'api']
        ]
    ]
]);
```

---

## Frontend Dev Mode Flip

When working with a modern JS frontend (e.g. Vite, webpack), you typically want two behaviors:

- **Development**: proxy requests live to the dev server (so hot module replacement works).
- **Production / staging**: serve the pre-built static files directly from Tent.

Tent has no built-in knowledge of this distinction, but you can implement it yourself by reading an environment variable inside the rule file.

### Environment variable

Set `FRONTEND_DEV_MODE=true` in your `.env` for development and omit it (or set it to `false`) for production.

### Rule file

```php
<?php

if (getenv('FRONTEND_DEV_MODE') === 'true') {
    // Development: proxy live requests to the Vite dev server
    Configuration::buildRule([
        'handler' => [
            'type' => 'proxy',
            'host' => 'http://frontend:8080'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/',               'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets/js/',     'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/assets/css/',    'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@vite/',         'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/node_modules/',  'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@react-refresh', 'type' => 'exact']
        ]
    ]);
    // Images are still served statically even in dev mode
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/assets/images/', 'type' => 'begins_with']
        ]
    ]);
} else {
    // Production: serve pre-built static files from /var/www/html/static
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets',     'type' => 'begins_with'],
        ]
    ]);
    // Map / to /index.html for SPA routing
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
        ],
        'middlewares' => [
            [
                'class' => 'Tent\Middlewares\SetPathMiddleware',
                'path'  => '/index.html'
            ]
        ]
    ]);
}
```

### Why this works

Rules are evaluated at request time, but `getenv()` is resolved at boot — when PHP parses the configuration. As long as the `FRONTEND_DEV_MODE` environment variable is set correctly before the container starts, Tent will load the right set of rules.

---

## Static Files

Place any assets you want Tent to serve directly (images, committed CSS, etc.) into the folder you mount at `/var/www/html/static/`.

If your frontend build tool (e.g. Vite) writes its output to a different path, share a volume between the build container and the Tent container so built files land directly in the static root without a copy step:

```yaml
volumes:
  - ./docker_volumes/static/index.html:/var/www/html/static/index.html
  - ./docker_volumes/static/assets/js/:/var/www/html/static/assets/js/
  - ./docker_volumes/static/assets/css/:/var/www/html/static/assets/css/
```

The Vite container writes to `./docker_volumes/static/` as its `outDir`, and Tent picks it up immediately.

---

## Complete Example Layout

```
my-project/
├── docker-compose.yml
├── .env                          # FRONTEND_DEV_MODE=true
├── proxy/
│   └── static/
│       └── assets/
│           └── images/           # committed static images
├── proxy_configuration/          # mounted into Tent at /var/www/html/configuration/
│   ├── configure.php
│   └── rules/
│       ├── backend.php
│       └── frontend.php
└── docker_volumes/
    ├── cache/                    # FileCacheMiddleware writes here
    └── static/                   # Vite build output, shared with Tent
        ├── index.html
        └── assets/
            ├── js/
            └── css/
```

### `docker-compose.yml`

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    ports:
      - "0.0.0.0:80:80"
    volumes:
      - ./proxy/static/:/var/www/html/static/
      - ./proxy_configuration/:/var/www/html/configuration/
      - ./docker_volumes/static/:/var/www/html/static/built/
      - ./docker_volumes/cache/:/var/www/html/cache/
    links:
      - api:api
      - frontend:frontend
    env_file: .env

  api:
    image: myapp/api:latest

  frontend:
    image: myapp/frontend:latest
```

### `proxy_configuration/configure.php`

```php
<?php

require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
```

### `proxy_configuration/rules/backend.php`

```php
<?php

// Read endpoints — cached
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => './cache'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);

// Write endpoints — no cache
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => false
    ],
    'matchers' => [
        ['method' => 'POST',   'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'PUT',    'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'DELETE', 'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'PATCH',  'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

---

## Reference

### Container paths

| Path inside container          | Purpose                                      |
|--------------------------------|----------------------------------------------|
| `/var/www/html/configuration/` | PHP rule files (mount your config here)      |
| `/var/www/html/static/`        | Static files served by `StaticFileHandler`   |
| `./cache` (default, relative)  | File cache written by `FileCacheMiddleware`  |

### Handlers

| `type`          | Class                          | What it does |
|-----------------|--------------------------------|--------------|
| `default_proxy` | `DefaultProxyRequestHandler`   | Proxy with automatic Host header fix and file cache |
| `proxy`         | `ProxyRequestHandler`          | Bare proxy — no default middlewares |
| `static`        | `StaticFileHandler`            | Serve files from a local directory |

### Middlewares

| Class                                   | What it does |
|-----------------------------------------|--------------|
| `Tent\Middlewares\FileCacheMiddleware`   | Cache upstream responses to disk; serve on subsequent requests |
| `Tent\Middlewares\SetHeadersMiddleware` | Set or override request headers before forwarding |
| `Tent\Middlewares\RenameHeaderMiddleware` | Move a header value to a different header name |
| `Tent\Middlewares\SetPathMiddleware`    | Rewrite the request path before the handler runs |

### Cache matchers

| Class                              | What it does |
|------------------------------------|--------------|
| `Tent\Matchers\StatusCodeMatcher`  | Cache only responses matching specified status codes or patterns (`'2xx'`, `200`, `301`) |
| `Tent\Matchers\RequestMethodMatcher` | Cache only requests with specified HTTP methods (`GET`, `HEAD`) |

### Rule matchers

| `type`        | Behavior                                        |
|---------------|-------------------------------------------------|
| `exact`       | Match URI exactly                               |
| `begins_with` | Match URI prefix                                |
