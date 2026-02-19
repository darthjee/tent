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

Called after the handler produces a response. Use this to:

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

**Example: Prepend a path prefix**

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
        'type' => 'proxy',
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

See [FileCacheMiddleware Matchers](file-cache-middleware-matchers.md) for detailed matcher configuration.

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

### Performance Considerations

- **Cache early**: Place `FileCacheMiddleware` at the start of the middlewares list so cached requests are resolved without forwarding to the backend.
- **Keep middlewares lightweight**: `processRequest` runs on every incoming request, so avoid expensive operations like database calls unless necessary.
- **Use specific matchers**: Narrow the conditions under which a middleware applies (e.g., only cache `GET` requests with `200` responses) to avoid unintended behaviour.
