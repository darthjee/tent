# Request Handlers

## `default_proxy` — recommended proxy handler

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

### Options

| Option       | Type             | Required | Default     | Description |
|--------------|------------------|----------|-------------|-------------|
| `host`       | `string`         | Yes      | —           | Upstream backend URL |
| `cache`      | `string\|false`  | No       | `'./cache'` | Cache directory path, or `false` to disable |
| `cacheCodes` | `array`          | No       | `['2xx']`   | HTTP status codes/patterns to cache |
| `skip_cache_header` | `string`   | No       | —           | Request header name that bypasses cache read/write when present |

---

## `proxy` — low-level proxy handler

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

## `static` — serve files from disk

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

## Which handler should I use?

| Scenario | Handler |
|----------|---------|
| Proxying to a backend API (standard case) | `default_proxy` |
| Proxying to a dev server (e.g. Vite HMR) without cache | `proxy` |
| Serving pre-built JS/CSS/images from disk | `static` |
| Full custom middleware stack | `proxy` |

In almost all backend proxy scenarios, prefer `default_proxy`. Only drop down to `proxy` when you explicitly need to change or omit the default middleware behavior.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
