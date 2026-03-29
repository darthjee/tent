# Tent Documentation

All documentation for the Tent project. See [AGENTS.md](../AGENTS.md) for a summary table and quick-start commands.

## Guides

| File | Contents |
|------|----------|
| [Architecture](architecture.md) | Source layout, key components, configuration patterns, class loading, dev API/frontend, testing conventions. |
| [Runtime Flow](flow.md) | Entry point, request lifecycle, execution path from Apache to response. |
| [Request Handlers](request-handlers.md) | Differences between `default_proxy`, `proxy`, and `static`, including options and examples. |
| [Creating Middlewares](creating-middlewares.md) | How to build custom middlewares; interface, short-circuiting, built-in middlewares. |
| [FileCacheMiddleware Matchers](file-cache-middleware-matchers.md) | Matcher configuration for `FileCacheMiddleware`; migration from deprecated `httpCodes`. |
| [Adding Request Matchers](adding-request-matchers.md) | How to add new `RequestMatcher` classes. |

## Issues and Plans

| Directory | Contents |
|-----------|----------|
| [issues/](issues/) | One file per open GitHub issue — background, task, and acceptance criteria. |
| [plans/](plans/) | One directory per planned/in-progress issue — step-by-step implementation plan. |

### Naming conventions

**Issues:** `docs/issues/<github_issue_id>_<issue-name>.md`
Example: `docs/issues/42_add-negative-matcher.md`

**Plans:** `docs/plans/<github_issue_id>_<topic>/plan.md`
Example: `docs/plans/42_add-negative-matcher/plan.md`

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
