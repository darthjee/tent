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

## Bypass cache from a middleware

A custom middleware can bypass the cache for a single request by calling `skipCache()` on the `ProcessingRequest`. The flag is proxy-internal: it is one-way (it cannot be cleared), it is never sent as a header to the upstream or the client, and it cannot be set from configuration.

```php
use Tent\Middlewares\Middleware;
use Tent\Models\ProcessingRequest;

class SkipCacheForAdminsMiddleware extends Middleware
{
    public static function build(array $attributes): self
    {
        return new self();
    }

    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        if (str_starts_with($request->requestPath(), '/admin/')) {
            $request->skipCache();
        }

        return $request;
    }
}
```

Declare the guard in `prependMiddlewares` so it runs before the handler's built-in `FileCacheMiddleware`:

```php
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => './cache/api'
    ],
    'prependMiddlewares' => [
        ['class' => 'SkipCacheForAdminsMiddleware']
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/', 'type' => 'begins_with']
    ]
]);
```

When the flag is set, `FileCacheMiddleware` performs no cache read, no cache write and no `evictOn` eviction, and `CacheStalenessMiddleware` schedules no background refresh. `CacheCleanupMiddleware` ignores the flag.

**Placement matters.** A full bypass (no read and no write) works only from `prependMiddlewares`. If the guard is declared in `middlewares`, it runs after the built-in `FileCacheMiddleware`, which short-circuits the chain on a cache hit; in that position only the write of a cache miss is skipped on a `default_proxy` handler. On a hit the guard does not run, so a configured `CacheStalenessMiddleware` still refreshes a stale entry.

> **Warning**: every flagged request goes upstream. If your trigger is client-controllable, so is the bypass, and a client can use it to amplify load on your backend.

## Path safety

Cache entries are stored under `<location>/<request path>/<METHOD>`, so a request which could traverse outside the cache root, `location`, is never cached. This is the case when its path or its HTTP method has:

- a segment which is exactly `.` or `..`, as in `/users/../admin`;
- a NUL byte.

Such a request is proxied upstream as usual, just without caching: nothing is read from the cache, nothing is written to it and no directory is created. A `warn` line is logged (`cache bypassed — path: <path>, method: <method>, reason: unsafe path`). The check applies to the path the cache receives, so it also covers paths rewritten by a middleware. In the shipped Apache image, dot segments in the raw request line can reach tent, which is why tent checks them itself.

The same rule applies to the cleanup targets of [`CacheCleanupMiddleware`](./middlewares.md#cachecleanupmiddleware): an unsafe path is refused with a `warn` line (`cache cleanup refused — path: <path>, reason: unsafe path`) and nothing is deleted for it.

Only whole segments are matched, so the following are not unsafe:

- names such as `...`, `..foo` or `a..b`;
- dot-prefixed names such as `.git`;
- percent-encoded sequences such as `%2e%2e`: tent does not URL-decode the path, so in its cache path they are literal names.

> **Trust boundary**: the cache directory must still be writable only by tent, as symbolic links are checked when a response is stored, but not when it is read:
>
> - storing a response never follows a symbolic link out of `location`. If the entry directory resolves outside it, nothing is stored, a `warn` line is logged (`cache store skipped — path: <path>, method: <method>, reason: resolves outside cache location`) and the response itself is unaffected;
> - a symbolic link standing in place of a cache file is replaced by a regular file when tent writes that file, leaving the link's target untouched;
> - `location` itself may be a symbolic link (e.g. to a Docker volume), and links resolving inside it keep working;
> - tent creates cache directories with mode `0755` and cache files with mode `0644`, so a permissive umask cannot make them group- or world-writable. Files are `0644` whatever the umask, while a stricter umask can further restrict directories;
> - cache **reads** are not checked for symbolic links, to keep cache hits free of extra filesystem calls. A hit can therefore be served from outside `location`, and what follows a hit (e.g. [`CacheStalenessMiddleware`](./middlewares.md#cachestalenessmiddleware) removing a stale entry) is not confined to it either.

## Path length

Since each segment of the request path becomes a directory name, a request which the filesystem cannot hold is never cached. This is the case when:

- a segment of its path, or its HTTP method, is longer than 255 bytes;
- the path of its cache file, including the cache `location`, would be longer than 4094 bytes (the longest path PHP accepts where `PATH_MAX` is 4096);
- the name of its cache file would be longer than 255 bytes, which only a [custom request hasher](./creating-request-hashers.md) returning a long hash can cause.

Both cache file limits include 21 bytes reserved for the temporary files used while writing, so a hash may have up to 224 bytes. Lengths are counted in bytes on the path as received, which tent does not URL-decode: `%41` counts as 3 bytes, and duplicate slashes count too.

Such a request is proxied upstream as usual, just without caching: the response is unaffected, and nothing is read from, written to or removed from the cache. A single `warn` line is logged (`cache bypassed — path: <path>, method: <method>, reason: path too long`), where a path (or method) longer than 200 bytes is cut to its first 200 bytes, followed by `… (<N> bytes)`, `<N>` being its full length. A request which is also unsafe (see [Path safety](#path-safety)) is only reported as such.

Any other failure to create a cache directory (e.g. permissions, a full or read-only disk, or a filesystem with lower limits) only skips the store too: a `warn` line is logged (`cache store skipped — path: <path>, method: <method>, reason: <error message>`) and the response is never affected.

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

If you also use [`CacheStalenessMiddleware`](./middlewares.md#cachestalenessmiddleware) for the same `location`, its background-refresh writes reuse `FileCacheMiddleware`'s header filter automatically; its own header-filter options are deprecated (ignored, with a warning logged). `evictOn` (or `evict_on`) is not shared: use matching lists on both middlewares so that entries evicted on read/write by `FileCacheMiddleware` and entries evicted when a background refresh receives a matching status code follow the same rule. It is disabled by default. A [failed refresh](./middlewares.md#failed-refreshes) (a transport error, including an interrupted or partial transfer, or an exception from the upstream request) keeps the stale entry, whatever its status code. If the PHP process running a refresh dies, [`sentinel_ttl_seconds`](./middlewares.md#cachestalenessmiddleware) (`60` by default) sets how long it takes before another refresh of that entry can be started.

## Manual `FileCacheMiddleware` setup

When using `proxy` instead of `default_proxy`, configure `FileCacheMiddleware` explicitly. A cache hit skips every request middleware listed after it, and a [`CacheStalenessMiddleware`](./middlewares.md#cachestalenessmiddleware) refresh does not include their changes either, so list the ones the upstream needs, such as the `Host` header ones, **before** it:

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
        // Fix the Host header for the backend
        [
            'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
            'from'  => 'Host',
            'to'    => 'X-Forwarded-Host'
        ],
        [
            'class'   => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'api']
        ],
        // Then cache — short-circuits on hit, skipping the backend entirely
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
