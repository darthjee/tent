# Tent Documentation

Welcome to the Tent documentation.

## Guides

| File | Contents |
|------|----------|
| [Request Handlers](request-handlers.md) | Differences between `default_proxy`, `proxy`, and `static`, including options and examples. |
| [Creating Middlewares](creating-middlewares.md) | How to build custom middlewares; interface, short-circuiting, built-in middlewares. |
| [FileCacheMiddleware Matchers](file-cache-middleware-matchers.md) | Matcher configuration for `FileCacheMiddleware`; migration from deprecated `httpCodes`. |
| [Adding Request Matchers](adding-request-matchers.md) | How to add new `RequestMatcher` classes. |

## Quick Reference

### Built-in Middlewares

| Middleware | Description | Key Option |
|-----------|-------------|------------|
| `Tent\Middlewares\SetHeadersMiddleware` | Sets or overrides request headers | `headers` (array) |
| `Tent\Middlewares\SetPathMiddleware` | Rewrites the request path | `path` (string) |
| `Tent\Middlewares\RenameHeaderMiddleware` | Renames a request header | `from`, `to` (strings) |
| `Tent\Middlewares\FileCacheMiddleware` | Caches responses to disk | `location` (string), `matchers` (array) |

### Available Matchers (for FileCacheMiddleware)

| Matcher | Description | Key Option |
|---------|-------------|------------|
| `Tent\Matchers\StatusCodeMatcher` | Matches by HTTP response status code | `httpCodes` (array) |
| `Tent\Matchers\RequestMethodMatcher` | Matches by HTTP request method | `requestMethods` (array) |

### Minimal Configuration Example

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```
