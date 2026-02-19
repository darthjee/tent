# FileCacheMiddleware Matchers

`FileCacheMiddleware` caches backend responses to disk and serves them on subsequent requests. You can control exactly which responses are cached by configuring **matchers**.

## Table of Contents

- [Overview](#overview)
- [Matcher Configuration Migration](#matcher-configuration-migration)
- [Configuring Matchers](#configuring-matchers)
- [Available Matcher Types](#available-matcher-types)
- [How Matchers Determine Caching](#how-matchers-determine-caching)
- [Complete Configuration Examples](#complete-configuration-examples)
- [Cache Location and Structure](#cache-location-and-structure)

---

## Overview

`FileCacheMiddleware` performs two roles:

1. **On request** (`processRequest`): Checks whether a cached file exists for the incoming request path. If found, the cached response is loaded and returned immediately, skipping the backend.
2. **On response** (`processResponse`): After the handler returns a response, checks whether it should be cached. If all configured matchers pass, the response is written to disk.

The middleware is placed in the `middlewares` array of a rule configuration:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
                    'httpCodes' => [200]
                ]
            ]
        ]
    ]
]);
```

---

## Matcher Configuration Migration

### Deprecated: `httpCodes` attribute

The old `httpCodes` attribute directly on `FileCacheMiddleware` is **deprecated**. It still works but will trigger a deprecation warning in the logs:

```php
// Old (deprecated) — do not use
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'httpCodes' => [200]
]
```

### New: `matchers` array

Use the `matchers` array for a more flexible and explicit configuration:

```php
// New (recommended)
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ]
    ]
]
```

### Side-by-Side Comparison

| Feature | Old (`httpCodes`) | New (`matchers`) |
|---------|-------------------|------------------|
| Status code filtering | `'httpCodes' => [200, 301]` | `StatusCodeMatcher` with `'httpCodes' => [200, 301]` |
| Request method filtering | `'requestMethods' => ['GET']` | `RequestMethodMatcher` with `'requestMethods' => ['GET']` |
| Multiple conditions | Not supported | Combine multiple matchers in the array |
| Deprecation warning | Yes (if used) | No |

---

## Configuring Matchers

Matchers are defined as an array of associative arrays inside the `matchers` key of the middleware configuration. Each matcher has a `class` key and matcher-specific options.

### Single Matcher

```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ]
    ]
]
```

### Multiple Matchers

When multiple matchers are configured, **all** matchers must pass for a response to be cached (logical AND):

```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ],
        [
            'class' => 'Tent\\Matchers\\RequestMethodMatcher',
            'requestMethods' => ['GET']
        ]
    ]
]
```

---

## Available Matcher Types

### `StatusCodeMatcher`

Matches responses based on their HTTP status code.

**Class**: `Tent\Matchers\StatusCodeMatcher`

| Option | Type | Description |
|--------|------|-------------|
| `httpCodes` | `array` | List of status codes or patterns to match. Defaults to `[200]`. |

**Supported code formats**:

- **Exact code**: `200`, `301`, `404` — matches that specific status code
- **Pattern**: `"2xx"` — matches any status code in the 200–299 range

```php
[
    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
    'httpCodes' => [200, 301]
]
```

```php
[
    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
    'httpCodes' => ['2xx']
]
```

### `RequestMethodMatcher`

Matches based on the HTTP method of the original request.

**Class**: `Tent\Matchers\RequestMethodMatcher`

| Option | Type | Description |
|--------|------|-------------|
| `requestMethods` | `array` | List of HTTP methods to allow (case-insensitive). Defaults to `['GET']`. |

```php
[
    'class' => 'Tent\\Matchers\\RequestMethodMatcher',
    'requestMethods' => ['GET', 'HEAD']
]
```

---

## How Matchers Determine Caching

The evaluation logic works as follows:

1. On **response**: `FileCacheMiddleware::processResponse()` iterates over all configured matchers and calls `matchResponse()` on each. If any matcher returns `false`, the response is **not** cached. Only when **all** matchers return `true` is the response written to disk.

2. On **request**: `FileCacheMiddleware::processRequest()` iterates over all configured matchers and calls `matchRequest()` on each. If any matcher returns `false`, the cache check is skipped entirely for that request. Only when **all** matchers pass does the middleware attempt to read a cached file.

This means matchers apply symmetrically: the same conditions that determine whether a response is stored also determine whether the cache is consulted for future requests.

---

## Complete Configuration Examples

### Cache only successful responses

```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ]
    ]
]
```

### Cache all 2xx and redirect responses

```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => ['2xx', 301, 302]
        ]
    ]
]
```

### Cache only GET requests with 200 responses

```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ],
        [
            'class' => 'Tent\\Matchers\\RequestMethodMatcher',
            'requestMethods' => ['GET']
        ]
    ]
]
```

### Full rule with FileCacheMiddleware and SetHeadersMiddleware

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api.com:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
                    'httpCodes' => [200, 301]
                ]
            ]
        ],
        [
            'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'api.com'
            ]
        ]
    ]
]);
```

---

## Cache Location and Structure

The `location` option specifies the directory where cached response files are stored. This path is relative to the Tent application root (or absolute).

```php
'location' => './cache'
// Resolves to: <app_root>/cache/
```

In the default development setup, cache files are stored in `docker_volumes/cache/` (mapped into the container).

Cached files are named based on a hash of the request path, ensuring that each unique URL maps to a unique cache file. The directory is created automatically if it does not exist.

> **Note**: There is currently no built-in cache expiry or invalidation mechanism. To clear the cache, delete the files in the configured `location` directory.
