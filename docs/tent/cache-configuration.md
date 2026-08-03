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

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
