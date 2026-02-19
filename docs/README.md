# Tent Documentation

Welcome to the Tent documentation. This index provides an overview of all available guides and references.

## Contents

### Configuration

- **[Main README](../README.md)** — Getting started, architecture overview, and setup instructions

### Middlewares

- **[Creating Middlewares](creating-middlewares.md)** — Learn how to build custom middlewares to process requests and responses
  - What middlewares are and how they work
  - The `processRequest` and `processResponse` interface
  - Step-by-step guide to creating a custom middleware
  - Short-circuiting the request chain
  - Configuration in `docker_volumes/configuration/configure.php`
  - Built-in middlewares: `SetHeadersMiddleware`, `SetPathMiddleware`, `FileCacheMiddleware`
  - Best practices and performance tips

- **[FileCacheMiddleware Matchers](file-cache-middleware-matchers.md)** — Configure response caching with matchers
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
        ],
        [
            'class' => 'Tent\\Middlewares\\SetHeadersMiddleware',
            'headers' => ['Host' => 'api.internal']
        ]
    ]
]);
```
