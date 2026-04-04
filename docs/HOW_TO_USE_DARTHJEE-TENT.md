# How to Use darthjee/tent

[Tent](https://github.com/darthjee/tent) is a PHP-based reverse proxy and static file server distributed as a Docker image. It acts as the single entry point for applications that combine a backend API and a frontend — routing, caching, and serving files through a simple PHP configuration layer.

---

## Quick Start with Docker

Pull the image and run it:

```yaml
services:
  proxy:
    image: darthjee/tent:0.4.3
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
use Tent\Models\Rule;
use Tent\Handlers\FixedFileHandler;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Handlers\StaticFileHandler;
use Tent\Models\Server;
use Tent\Models\FolderLocation;
use Tent\Models\RequestMatcher;

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

### Handler types

#### `proxy` — forward the request to an upstream server

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://backend:8080'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

#### `static` — serve files from a local folder

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

Tent maps the URI path to a file inside `location`. A request for `/assets/js/app.js` will serve `/var/www/html/static/assets/js/app.js`.

### Matcher types

| `type`        | Behavior                                          |
|---------------|---------------------------------------------------|
| `exact`       | Matches only if the URI is exactly equal          |
| `begins_with` | Matches if the URI starts with the given prefix   |

Matchers also accept a `method` field (`GET`, `POST`, etc.).

---

## Adding Middlewares

Middlewares wrap the handler and can modify requests or responses.

### `FileCacheMiddleware` — cache responses to disk

Avoids hitting the upstream for repeated identical requests. The `matchers` inside the middleware control which responses get cached (typically only 2xx):

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://backend:8080'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    "middlewares" => [
        [
            'class' => 'Tent\Middlewares\FileCacheMiddleware',
            'location' => "./cache",
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => ["2xx"]
                ]
            ]
        ]
    ]
]);
```

### `SetHeadersMiddleware` — inject headers into the request

Useful for setting `Host` or any other header before forwarding upstream:

```php
"middlewares" => [
    [
        'class' => 'Tent\Middlewares\SetHeadersMiddleware',
        'headers' => [
            'Host' => 'localhost'
        ]
    ]
]
```

### `SetPathMiddleware` — rewrite the path before serving

Useful for mapping `/` to `/index.html` when serving an SPA:

```php
"middlewares" => [
    [
        'class' => 'Tent\Middlewares\SetPathMiddleware',
        'path' => '/index.html'
    ]
]
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
            ['method' => 'GET', 'uri' => '/',              'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets/js/',    'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/assets/css/',   'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@vite/',        'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/node_modules/', 'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@react-refresh','type' => 'exact']
        ]
    ]);
    // Images are still served statically even in dev mode
    Configuration::buildRule([
        'handler' => [
            'type' => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/assets/images/', 'type' => 'begins_with'],
        ]
    ]);
} else {
    // Production: serve pre-built static files from /var/www/html/static
    Configuration::buildRule([
        'handler' => [
            'type' => 'static',
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
            'type' => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
        ],
        "middlewares" => [
            [
                'class' => 'Tent\Middlewares\SetPathMiddleware',
                'path' => '/index.html'
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
    └── static/                   # Vite build output, shared with Tent
        ├── index.html
        └── assets/
            ├── js/
            └── css/
```

---

## Reference

| Path inside container        | Purpose                            |
|------------------------------|------------------------------------|
| `/var/www/html/configuration/` | PHP rule files (mount your config here) |
| `/var/www/html/static/`      | Static files served by Tent        |
| `./cache` (relative)         | File cache written by `FileCacheMiddleware` |

| Class / type                         | What it does                                         |
|--------------------------------------|------------------------------------------------------|
| `handler.type = proxy`               | Forward request to an upstream HTTP host             |
| `handler.type = static`              | Serve file from a local directory                    |
| `matcher.type = exact`               | Match URI exactly                                    |
| `matcher.type = begins_with`         | Match URI prefix                                     |
| `Tent\Middlewares\FileCacheMiddleware`   | Cache upstream responses to disk                 |
| `Tent\Middlewares\SetHeadersMiddleware` | Inject headers before forwarding                 |
| `Tent\Middlewares\SetPathMiddleware`    | Rewrite the request path before serving          |
| `Tent\Matchers\StatusCodeMatcher`      | Match responses by HTTP status code range        |
