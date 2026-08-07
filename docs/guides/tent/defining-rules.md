# Defining Rules

Each rule is registered with `Configuration::buildRule()`. A rule has three parts:

- **`handler`** — what to do with the request (proxy it, serve a file, serve a folder).
- **`matchers`** — which requests this rule applies to.
- **`middlewares`** (optional) — transformations applied before or after the handler.
- **`prependMiddlewares`** (optional) — same shape as `middlewares`, but its entries run *before* the handler's built-in default middlewares (if any) instead of after.

## Matcher types

| `type`        | Behavior                                          |
|---------------|---------------------------------------------------|
| `exact`       | Matches only if the URI is exactly equal          |
| `begins_with` | Matches if the URI starts with the given prefix   |
| `ends_with`   | Matches if the URI ends with the given suffix     |
| `regex`       | Matches if the URI matches a regular expression   |

Matchers also accept a `method` field (`GET`, `POST`, `PUT`, `DELETE`, etc.). When `method` is omitted, the rule matches any HTTP method for the given URI pattern.

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
