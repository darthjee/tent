# Request Handlers

This guide explains the available `RequestHandler` types in Tent, when to use each one, and which options they support.

## Table of Contents

- [Overview](#overview)
- [Which Handler Should I Use?](#which-handler-should-i-use)
- [DefaultProxyRequestHandler (`default_proxy`)](#defaultproxyrequesthandler-default_proxy)
- [ProxyRequestHandler (`proxy`)](#proxyrequesthandler-proxy)
- [StaticFileHandler (`static`)](#staticfilehandler-static)
- [Using `class` Instead of `type`](#using-class-instead-of-type)
- [Middleware Order](#middleware-order)
- [MissingRequestHandler (404 Fallback)](#missingrequesthandler-404-fallback)

---

## Overview

Tent resolves handlers through `RequestHandler::build()`.

Supported handler `type` values are:

- `default_proxy`
- `proxy`
- `static`

You can also provide a custom handler class via the `class` key.

---

## Which Handler Should I Use?

- Use **`default_proxy`** for standard backend proxy rules.
- Use **`proxy`** only when you need full control of middleware behavior.
- Use **`static`** to serve files from a directory.

In most proxy scenarios, `default_proxy` should be your default choice.

---

## DefaultProxyRequestHandler (`default_proxy`)

`DefaultProxyRequestHandler` is the recommended proxy handler.

It automatically adds:

1. `RenameHeaderMiddleware('Host', 'X-Forwarded-Host')`
2. `SetHeadersMiddleware(['Host' => <configured host>])`
3. `FileCacheMiddleware(...)` (unless cache is disabled)

### Options

| Option | Type | Required | Default | Description |
|---|---|---|---|---|
| `host` | `string` | Yes | — | Backend host used for proxying |
| `cache` | `string \| false` | No | `'./cache'` | Cache directory, or `false` to disable cache |
| `cacheCodes` | `array` | No | `['2xx']` | Status codes/patterns used by `StatusCodeMatcher` |

### Example: Default proxy with built-in cache

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ]
]);
```

### Example: Disable cache

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'cache' => false
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ]
]);
```

### Example: Custom cache location and status code match

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'cache' => './cache/api',
        'cacheCodes' => [200, 301]
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ]
]);
```

---

## ProxyRequestHandler (`proxy`)

`ProxyRequestHandler` is a lower-level proxy handler.

It only forwards the request as-is (plus any middlewares you explicitly configure). It does **not** add default header/caching middlewares automatically.

Use this handler when you want custom behavior that differs from `default_proxy`.

### Options

| Option | Type | Required | Default | Description |
|---|---|---|---|---|
| `host` | `string` | Yes (practical) | `''` | Backend host used for proxying |

### Example: Fully custom proxy stack

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\RenameHeaderMiddleware',
            'from' => 'Host',
            'to' => 'X-Forwarded-Host'
        ],
        [
            'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
            'headers' => ['Host' => 'api.internal']
        ],
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
                    'httpCodes' => ['2xx']
                ]
            ]
        ]
    ]
]);
```

---

## StaticFileHandler (`static`)

`StaticFileHandler` serves files from a folder location.

It validates paths and returns:

- `403 Forbidden` for invalid paths
- `404 Not Found` for missing files

### Options

| Option | Type | Required | Default | Description |
|---|---|---|---|---|
| `location` | `string` | Yes | `''` | Base folder for static files |

### Example: Serve static frontend files

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'static',
        'location' => '/var/www/html/static/'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\SetPathMiddleware',
            'path' => '/index.html'
        ]
    ]
]);
```

---

## Using `class` Instead of `type`

You can define the handler explicitly with a class name:

```php
use Tent\RequestHandlers\DefaultProxyRequestHandler;

Configuration::buildRule([
    'handler' => [
        'class' => DefaultProxyRequestHandler::class,
        'host' => 'http://api:80',
        'cache' => false
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ]
]);
```

This is useful when you want to be explicit or when using custom handler classes.

---

## Middleware Order

For all handlers, middlewares listed in rule configuration are applied in order.

With `default_proxy`, there is an extra detail:

1. Internal default middlewares are created first (inside the handler constructor)
2. Rule-level middlewares are appended afterward

So rule-level middlewares run **after** the built-in default middlewares.

---

## MissingRequestHandler (404 Fallback)

`MissingRequestHandler` is used internally when no rule matches the request.

It always returns a `404 Not Found` response.

You usually do not configure it directly in rules.
