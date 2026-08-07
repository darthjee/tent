# Plan: Allow the prepending of middlewares

Issue: [273-allow-the-prepending-of-middlewares.md](../../issues/273-allow-the-prepending-of-middlewares.md)

## Overview

Add a new, optional `prependMiddlewares` rule key so custom middlewares can run *before* a request handler's built-in default middlewares, instead of always being appended after them via the existing `middlewares` key. Implemented generically at the base `RequestHandler`/`Rule` level so every handler type gets it, fully backward compatible, then documented for developers extending the proxy.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **Rule key**: `prependMiddlewares` — same array shape as the existing `middlewares` key: an array of associative arrays, each with a `class` key (fully-qualified middleware class name) plus whatever other keys that middleware's `build()` accepts, optionally including its own `matchers`.
- **Semantics**: entries in `prependMiddlewares` are inserted *before* the handler's built-in default middlewares (if any), preserving their own relative order (first entry in `prependMiddlewares` runs first). Entries in `middlewares` keep today's behavior — appended after the defaults. When `prependMiddlewares` is omitted, behavior is unchanged.
- **Example** `product-dev-extending` should use verbatim when documenting:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
    ],
    'prependMiddlewares' => [
        // runs BEFORE the handler's default middlewares
        ['class' => 'Tent\\Middlewares\\SetHeadersMiddleware', 'headers' => ['X-Api-Key' => 'secret']],
    ],
    'middlewares' => [
        // still runs AFTER the handler's default middlewares (unchanged)
    ],
]);
```
