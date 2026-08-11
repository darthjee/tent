# Cache Configuration

## Cache enabled (default)

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

## Cache disabled

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

## Custom cache location and codes

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

## Bypass cache with request header

When you need to force fresh responses for specific calls, configure `skip_cache_header`. Any request containing this header skips cache reads and writes for that request lifecycle:

```php
Configuration::buildRule([
    'handler' => [
        'type'              => 'default_proxy',
        'host'              => 'http://api:3000',
        'cache'             => './cache/api',
        'skip_cache_header' => 'X-Skip-Cache'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

## Require header before caching

When you only want to cache responses that opt in explicitly, configure `require_cache_header`. It is the write-only counterpart to `skip_cache_header`: it only checks the **response**, never the request, and gates the cache write — a response is only stored when the configured header is present in it:

```php
Configuration::buildRule([
    'handler' => [
        'type'                  => 'default_proxy',
        'host'                  => 'http://api:3000',
        'cache'                 => './cache/api',
        'require_cache_header'  => 'X-Cache-Allow'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

`skip_cache_header` and `require_cache_header` can be combined:

```php
'skip_cache_header'    => 'X-Skip-Cache',   // presence in request OR response → don't cache
'require_cache_header' => 'X-Cache-Allow',  // absence in response → don't cache (response-only)
```

If both are configured and both headers are found in the response, the response is **not** cached — `skip_cache_header` wins.

## Filter headers stored in cache

With zero configuration, a curated list of dangerous headers (`Set-Cookie`, `Set-Cookie2`, `WWW-Authenticate`, `Proxy-Authenticate`) is never written to cache. This stops a session cookie (or similar) received by one client from being replayed to a different client on a future cache hit.

To strip additional headers on top of the defaults, configure `additional_excluded_headers`:

```php
Configuration::buildRule([
    'handler' => [
        'type'                         => 'default_proxy',
        'host'                         => 'http://api:3000',
        'cache'                        => './cache/api',
        'additional_excluded_headers'  => ['X-Internal-Token']
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

To keep only an explicit set of headers instead, switch to allow mode with `mode: 'allow'` and `allowed_headers`:

```php
Configuration::buildRule([
    'handler' => [
        'type'            => 'default_proxy',
        'host'            => 'http://api:3000',
        'cache'           => './cache/api',
        'mode'            => 'allow',
        'allowed_headers' => ['Content-Type', 'Cache-Control']
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

`skip_cache_header`/`require_cache_header` decide **whether** a response is cached at all; `mode`/`excluded_headers`/`additional_excluded_headers`/`allowed_headers` decide which headers are stripped from what gets stored once a response is being cached — the two are independent and can be combined freely. See [Request Handlers](../../request-handlers.md#defaultproxyrequesthandler-default_proxy) for the full option table, including how `excluded_headers` fully overrides the default list and why an empty `allowed_headers` throws.

If you also use `CacheStalenessMiddleware` (see [Middlewares](./middlewares.md)) for the same `location`, configure it with the same header-filtering options — its background-refresh writes go through the same filter and should stay consistent with `FileCacheMiddleware`'s configuration.

## Manual `FileCacheMiddleware` setup

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
            'class'                 => 'Tent\Middlewares\FileCacheMiddleware',
            'location'              => './cache',
            'require_cache_header'  => 'X-Cache-Allow',
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

## Custom cache hash generator

By default, the cache key for a request is a SHA-256 hash of its query string only (`Tent\Cache\QueryRequestHasher`). Pass a `request_hasher` option (a `class` key, following the same pattern as matchers) to derive the cache key from other request data instead — for example, to key cached responses per authenticated caller via a request header:

> **Tip**: To keep noisy or irrelevant query parameters (e.g. tracking params) out of the cache key entirely, configure `filter_query_params` on `default_proxy` — see [`FilterQueryParamsMiddleware`](middlewares.md#filterqueryparamsmiddleware). It runs before `FileCacheMiddleware`, so the hash reflects the already-filtered query string.

```php
Configuration::buildRule([
    'handler' => [
        'type'           => 'default_proxy',
        'host'           => 'http://api:3000',
        'request_hasher' => [
            'class'      => 'Tent\Cache\HeaderAwareRequestHasher',
            'headerName' => 'X-Tenant-Id'
        ]
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

The same option is available on a manual `FileCacheMiddleware` entry:

```php
[
    'class'          => 'Tent\Middlewares\FileCacheMiddleware',
    'location'       => './cache',
    'request_hasher' => [
        'class'      => 'Tent\Cache\HeaderAwareRequestHasher',
        'headerName' => 'X-Tenant-Id'
    ]
]
```

See [Creating Request Hashers](./creating-request-hashers.md) for the full `RequestHasher` interface, security guidance, and a complete custom-hasher example.

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
