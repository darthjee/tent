# Request Handlers

## `default_proxy` — recommended proxy handler

`DefaultProxyRequestHandler` is the recommended handler for proxying requests to a backend. It automatically adds:

1. `RenameHeaderMiddleware('Host', 'X-Forwarded-Host')` — preserves the original `Host` header.
2. `SetHeadersMiddleware(['Host' => <configured host>])` — sets the correct `Host` for the upstream.
3. `FilterQueryParamsMiddleware` — filters the query string (only when `filter_query_params` is configured).
4. `FileCacheMiddleware` — caches successful responses to disk (unless disabled).

This means you get correct Host header handling and caching out of the box, with no extra configuration.

Rule-level `middlewares` are appended *after* these built-in defaults. If you need a custom middleware to run *before* them (e.g. before `Host` header rewriting), use `prependMiddlewares` instead — see [Defining Rules](defining-rules.md) and [Middleware Order](../../request-handlers.md#middleware-order).

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
| `require_cache_header` | `string` | No     | —           | Response header name required for a response to be cached (checked on the response only) |
| `filter_query_params` | `array` | No       | — (middleware not added) | Filters incoming query params; passed straight through to `FilterQueryParamsMiddleware::build()` — see [Middlewares](middlewares.md#filterqueryparamsmiddleware) |

Query filtering runs **before** caching, so the cache key reflects the already-filtered query string.

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

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
