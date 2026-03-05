# Tent Documentation

Welcome to the Tent documentation. This index provides an overview of all available guides and references.

## Contents

### Configuration

- **[Main README](../README.md)** — Getting started, architecture overview, and setup instructions
- **[Request Handlers](request-handlers.md)** — Differences between `default_proxy`, `proxy`, and `static`, including options and examples
- Prefer `DefaultProxyRequestHandler` (`'type' => 'default_proxy'`) for standard proxy rules
- Use `ProxyRequestHandler` (`'type' => 'proxy'`) only for custom middleware stacks

### Middlewares

- **[Built-in Middlewares](built-in-middlewares.md)** — Overview, options, and examples for all built-in middlewares
  - `SetHeadersMiddleware`, `SetPathMiddleware`, `RenameHeaderMiddleware` (quick reference)
  - Link to dedicated `FileCacheMiddleware` documentation

- **[Creating Middlewares](creating-middlewares.md)** — Learn how to build custom middlewares to process requests and responses
  - What middlewares are and how they work
  - The `processRequest` and `processResponse` interface
  - Step-by-step guide to creating a custom middleware
  - Short-circuiting the request chain
  - Configuration in `docker_volumes/configuration/configure.php`
  - Built-in middlewares: `SetHeadersMiddleware`, `SetPathMiddleware`, `FileCacheMiddleware`
  - Best practices and performance tips

- **[FileCacheMiddleware Matchers](file-cache-middleware-matchers.md)** — Configure response caching with matchers
  - Detailed reference for `FileCacheMiddleware`
  - Overview of `FileCacheMiddleware`
  - Migration from deprecated `httpCodes` to the new `matchers` array
  - Available matcher types: `StatusCodeMatcher`, `RequestMethodMatcher`
  - How matchers determine caching (AND logic)
  - Complete configuration examples
  - Cache location and structure

## Quick Reference

### Built-in Middlewares

| Middleware | Description | Key Option |
|-----------|-------------|------------|
| `Tent\Middlewares\SetHeadersMiddleware` | Sets or overrides request headers | `headers` (array) |
| `Tent\Middlewares\SetPathMiddleware` | Rewrites the request path | `path` (string) |
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
