# Creating Middlewares

Middlewares are components in Tent that can process requests before they reach handlers and/or process responses before they are sent to clients. They allow you to intercept, modify, or enrich the request/response lifecycle in a flexible and composable way.

## Table of Contents

- [What Are Middlewares?](#what-are-middlewares)
- [Middleware Interface](#middleware-interface)
- [How to Create a Custom Middleware](#how-to-create-a-custom-middleware)
- [Middleware Configuration](#middleware-configuration)
- [Built-in Middlewares](#built-in-middlewares)
- [Best Practices](#best-practices)

---

## What Are Middlewares?

Middlewares sit between the incoming HTTP request and the final request handler (proxy, static file, etc.). Each middleware can:

- **Modify the request** before it reaches the handler (e.g., change headers, rewrite paths)
- **Modify the response** before it is sent to the client (e.g., add headers, cache the response)
- **Short-circuit the chain** by providing a response directly, skipping the handler entirely (e.g., serving from cache)

Middlewares are executed in the order they are defined in the configuration, forming a processing chain.

---

## Middleware Interface

All middlewares extend the abstract `Tent\Middlewares\Middleware` base class and can override two methods:

### `processRequest(ProcessingRequest $request): ProcessingRequest`

Called before the request reaches the handler. Use this to:

- Modify request headers
- Rewrite the request path
- Read from cache and short-circuit the chain

```php
public function processRequest(ProcessingRequest $request): ProcessingRequest
{
    // Modify the request here
    return $request;
}
```

### `processResponse(Response $response): Response`

Called on every response before it is sent, whether it was produced by the handler or set by a request middleware (e.g. a cache hit). Use this to:

- Add or modify response headers
- Cache the response to disk
- Transform the response body

```php
public function processResponse(Response $response): Response
{
    // Modify the response here
    return $response;
}
```

The base class provides default no-op implementations for both methods, so you only need to override the ones relevant to your middleware.

---

## How to Create a Custom Middleware

### 1. Extend the Base Class

Create a class that extends `Tent\Middlewares\Middleware`:

```php
namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

class MyCustomMiddleware extends Middleware
{
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        // Custom request logic
        return $request;
    }

    public function processResponse(Response $response): Response
    {
        // Custom response logic
        return $response;
    }
}
```

### 2. Add a `build()` Factory Method

Middlewares are instantiated from configuration arrays using a static `build()` method. This method receives the raw configuration array and should return a new instance:

```php
public static function build(array $attributes): self
{
    $myOption = $attributes['myOption'] ?? 'default';
    return new self($myOption);
}
```

### 3. Implement Request Processing

Use `processRequest()` to modify the incoming request. The `ProcessingRequest` object provides methods such as:

- `$request->setHeader(string $name, string $value)` — Set or override a request header
- `$request->setRequestPath(string $path)` — Change the request path
- `$request->setResponse(Response $response)` — Short-circuit the chain with a pre-built response

#### Example: Prepend a path prefix

```php
public function processRequest(ProcessingRequest $request): ProcessingRequest
{
    $path = $request->requestPath();
    $request->setRequestPath('/api' . $path);
    return $request;
}
```

### 4. Short-Circuit the Request Chain

If your middleware can fully handle the request (e.g., returning a cached response), call `$request->setResponse()` to bypass the handler:

```php
public function processRequest(ProcessingRequest $request): ProcessingRequest
{
    $cachedResponse = $this->loadFromCache($request);

    if ($cachedResponse !== null) {
        $request->setResponse($cachedResponse);
    }

    return $request;
}
```

### 5. Implement Response Processing

Use `processResponse()` to inspect or modify the response after the handler runs:

```php
public function processResponse(Response $response): Response
{
    $response->setHeader('X-Powered-By', 'Tent');
    return $response;
}
```

### Complete Example

```php
namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

class AddCorrelationIdMiddleware extends Middleware
{
    private string $headerName;

    public function __construct(string $headerName = 'X-Correlation-Id')
    {
        $this->headerName = $headerName;
    }

    public static function build(array $attributes): self
    {
        return new self($attributes['headerName'] ?? 'X-Correlation-Id');
    }

    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $request->setHeader($this->headerName, uniqid('req-', true));
        return $request;
    }

    public function processResponse(Response $response): Response
    {
        $response->setHeader($this->headerName, 'processed');
        return $response;
    }
}
```

---

## Middleware Configuration

Middlewares are added to rules in `docker_volumes/configuration/configure.php` (or rule files in `docker_volumes/configuration/rules/`).

Each middleware is defined as an associative array with a `class` key pointing to the fully-qualified class name, plus any additional configuration options:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\MyCustomMiddleware',
            'myOption' => 'some-value'
        ],
        [
            'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'api.internal'
            ]
        ]
    ]
]);
```

Middlewares are executed in the order they appear in the array.

---

## Built-in Middlewares

### `SetHeadersMiddleware`

Sets or overrides request headers before the request is forwarded.

```php
[
    'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
    'headers' => [
        'Host' => 'backend.local',
        'X-Custom-Header' => 'value'
    ]
]
```

**When to use**: When the backend service expects a specific `Host` header, or when you need to inject custom headers for authentication or routing.

### `RenameHeaderMiddleware`

Renames a request header by copying its value to a new header name and removing the original.

```php
[
    'class' => 'Tent\\Middlewares\\RenameHeaderMiddleware',
    'from'  => 'Host',
    'to'    => 'X-Original-Host'
]
```

**When to use**: When you need to preserve the original value of a header under a different name before overriding it (e.g., saving the original `Host` as `X-Original-Host`).

### `SetPathMiddleware`

Rewrites the request path before it is processed by the handler.

```php
[
    'class' => 'Tent\\Middlewares\\SetPathMiddleware',
    'path' => '/index.html'
]
```

**When to use**: When serving static files with `StaticFileHandler` and you want all requests to a route to resolve to a specific file (e.g., single-page applications where `/` maps to `/index.html`).

### `FileCacheMiddleware`

Caches responses to disk and serves them on subsequent requests. Caching behaviour is controlled by matchers.

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

**When to use**: When proxying expensive or slow backend calls and you want to cache successful responses to reduce load and improve response times.

- `location` and `matchers` control *where* and *whether* a response is cached.
- `request_hasher` (optional) controls *how the cache key is derived* — a `RequestHasher` configuration (`class` key), following the same strategy pattern as matchers. Defaults to `Tent\Cache\QueryRequestHasher` (hash of the query string) when omitted. See [Creating Request Hashers](guides/tent/creating-request-hashers.md) for writing a custom one.

- A request can be excluded from caching entirely, either by the `skip_cache_header` handler option (driven by a client header) or by a middleware calling `$request->skipCache()` (proxy-internal, never a header). See [Bypass cache from a middleware](guides/tent/cache-configuration.md#bypass-cache-from-a-middleware).

See [FileCacheMiddleware Matchers](file-cache-middleware-matchers.md) for detailed matcher configuration.

### `CacheCleanupMiddleware`

Deletes stale `FileCacheMiddleware` cache directories on mutating requests (`POST`, `PATCH`, `PUT`, `DELETE`), before the request is forwarded upstream.

```php
[
    'class'    => 'Tent\\Middlewares\\CacheCleanupMiddleware',
    'location' => './cache',
    'clear'    => ['collection', 'entity'],
    'custom'   => [
        '/games/:game_slug/photo_upload' => [
            '/games.json',
            '/games/:game_slug.json',
        ]
    ]
]
```

- `location` must match the `location` used by the corresponding `FileCacheMiddleware`.
- `clear` (optional) selects which cache directories to delete: `collection` (the parent-resource cache dir) and/or `entity` (the specific resource's cache dir). Defaults to `['collection']` on `POST`, and `['collection', 'entity']` on `PATCH`/`PUT`/`DELETE`.
- `custom` (optional) maps a `:placeholder` route pattern to an explicit list of cache path templates to clear when a mutating request matches it. On a match, the captured placeholder values are substituted into every target template (e.g. the actual `game_slug` value fills in `:game_slug` in `/games/:game_slug.json`) before that concrete path's cache is cleared. More than one `custom` pattern may match the same request, and all matches apply. `custom` is additive — it never replaces `clear`'s `collection`/`entity` cleanup; both run for a matching mutating request.
  - Supported placeholders (by name, matched exactly or by suffix):
    - `:slug` or `:<anything>_slug` — letters, digits, dashes, underscores (`[A-Za-z0-9_-]+`).
    - `:id` or `:<anything>_id` — digits only (`[0-9]+`).
    - `:uuid` or `:<anything>_uuid` — canonical UUID shape (`[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}`).
  - Any other placeholder name is a configuration error.

**When to use**: Whenever `FileCacheMiddleware` caches `GET` responses for a resource that can also be written to — keeps cached collection/entity responses from going stale after a write. Use `custom` for routes that don't fit the `collection`/`entity` segment-count model, such as an action nested under an entity (e.g. `/games/:game_slug/photo_upload`) or a non-numeric entity segment.

### `CacheStalenessMiddleware`

Stale-while-revalidate for `FileCacheMiddleware`: a cached response older than `maxAgeSeconds` is still served as it is, and its entry is refreshed from the upstream for later requests.

```php
[
    'class' => 'Tent\\Middlewares\\CacheStalenessMiddleware',
    'location' => './cache',
    'host' => 'http://api:80',
    'maxAgeSeconds' => 300
]
```

- `location` (required) should match the `location` used by the corresponding `FileCacheMiddleware`. The entry checked and refreshed is always the one which was served.
- `host` (required) is the upstream base URL to re-contact when refreshing (e.g. the same `host` configured on the rule's `proxy`/`default_proxy` handler).
- `maxAgeSeconds` (or `max_age_seconds`) is the age, in seconds, past which a cached entry is considered stale. It defaults to `0`, so always set it.
- `evictOn` (or `evict_on`) optionally lists the status codes that remove the entry when a refresh receives them.
- A [failed refresh](guides/tent/middlewares.md#failed-refreshes) (a transport error, including an interrupted or partial transfer, or an exception from the upstream request) keeps the stale entry untouched, whatever its status code, and logs a warning.
- It acts in the response phase, and only on responses served from cache by `FileCacheMiddleware`: a response set by another middleware, a custom one included, never triggers a refresh. Configuring it **after** `FileCacheMiddleware` is recommended, although any position in the same handler works.
- The refresh write uses the header filter of that `FileCacheMiddleware`. The `mode`, `excluded_headers`, `additional_excluded_headers` and `allowed_headers` options of this middleware are **deprecated**: ignored, with a warning logged, and to be removed in the next major version.
- A sentinel file (`<meta-file>.refreshing`) debounces concurrent stale requests for the same entry while its refresh is in flight. A sentinel older than `sentinel_ttl_seconds` (e.g. left behind by a refresh that crashed) is taken over by the next stale hit, which refreshes the entry and logs a warning. The debounce is best effort, not a lock: the same entry may be refreshed more than once at a time.
- `sentinel_ttl_seconds` (or `sentinelTtlSeconds`, snake_case wins) is optional: an integer number of seconds, defaulting to `60` and not tied to `maxAgeSeconds`. With `0` or a negative value the sentinel never expires, which was the behaviour before this option existed. See [`CacheStalenessMiddleware`](guides/tent/middlewares.md#cachestalenessmiddleware) for details.
- The refresh is a direct request to `host`, not a new pass through the rule: see [the limitations of the refresh](guides/tent/middlewares.md#limitations-of-the-refresh) before enabling it.

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
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache'
        ],
        [
            'class' => 'Tent\\Middlewares\\CacheStalenessMiddleware',
            'location' => './cache',
            'host' => 'http://api:80',
            'maxAgeSeconds' => 300
        ]
    ]
]);
```

**When to use**: When serving slightly outdated content is acceptable, and cache entries should be renewed as they are requested.

**Caveat — "background" is FPM-dependent**: Tent has no real async/job-queue infrastructure. The refresh is deferred via `fastcgi_finish_request()` + `register_shutdown_function()` when running under PHP-FPM, so the client gets the stale response immediately and the refresh happens afterwards in the same PHP process. When `fastcgi_finish_request()` is not available (mod_php, CLI, built-in server), the refresh runs synchronously, before the response is sent. **This is the case of the shipped `php:8.4-apache` (mod_php) image**: there, a request hitting a stale entry waits for a full upstream round trip.

---

## Best Practices

### When to Use `processRequest` vs `processResponse`

| Goal | Method |
|------|--------|
| Modify headers sent to the backend | `processRequest` |
| Rewrite the request path | `processRequest` |
| Serve from cache (short-circuit) | `processRequest` |
| Add headers to the client response | `processResponse` |
| Cache the backend response | `processResponse` |
| Log or audit response details | `processResponse` |

### Order of Middleware Execution

Middlewares are applied in the order they are listed in the configuration:

- `processRequest` is called in **forward order** (first middleware runs first)
- `processResponse` is called in **forward order** as well (same order as processRequest)

Place middlewares that can short-circuit (like `FileCacheMiddleware`) **before** middlewares that modify the request, so cached responses are served without unnecessary processing.

The exception is a rule using [`CacheStalenessMiddleware`](#cachestalenessmiddleware): request middlewares skipped by a cache hit are also missing from the refresh, so those the upstream needs (e.g. `Host` or authentication headers) must come **before** `FileCacheMiddleware`. On `default_proxy`, that means `prependMiddlewares`; a custom `Host` requires a `proxy` rule (see [the limitations of the refresh](guides/tent/middlewares.md#limitations-of-the-refresh)).

This ordering advice applies within a single `middlewares` list. If a middleware needs to run ahead of a handler's own built-in defaults — for example, before `default_proxy`'s `Host` header rewriting — `middlewares` can't help, since those entries are always appended *after* the handler's defaults. Use the sibling `prependMiddlewares` rule key instead; its entries run *before* the handler's default middlewares while `middlewares` keeps running after them, unchanged. See [Defining Rules](guides/tent/defining-rules.md) and [Request Handlers — Middleware Order](request-handlers.md#middleware-order).

Cache guards, such as a middleware that calls `skipCache()`, belong in `prependMiddlewares`: only there do they run before the built-in `FileCacheMiddleware` and fully bypass the cache (read and write). See [Bypass cache from a middleware](guides/tent/cache-configuration.md#bypass-cache-from-a-middleware).

### Performance Considerations

- **Cache early**: Place `FileCacheMiddleware` at the start of the middlewares list so cached requests are resolved without forwarding to the backend (see the `CacheStalenessMiddleware` exception above).
- **Keep middlewares lightweight**: `processRequest` runs on every incoming request, so avoid expensive operations like database calls unless necessary.
- **Use specific matchers**: Narrow the conditions under which a middleware applies (e.g., only cache `GET` requests with `200` responses) to avoid unintended behaviour.
