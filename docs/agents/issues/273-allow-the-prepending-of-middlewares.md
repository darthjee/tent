# Issue: Allow the prepending of middlewares

## Description

Some request handlers — e.g. `DefaultProxyRequestHandler` (`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`) — come with their own built-in default middlewares (added during handler construction, via `initializeMiddlewares()`). `RequestHandler::build()` (`source/source/lib/request_handlers/RequestHandler.php:107-116`) builds the handler first — which wires up those defaults — and only afterward appends any user-configured `middlewares`. Execution order is strictly array-insertion order (both the request phase and the response phase iterate the same `$this->middlewares` array forward), so today there is no way to run a middleware *before* a handler's defaults.

## Problem

Adding a middleware via the `middlewares` rule key always puts it at the end of the stack, after the handler's defaults:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'cache' => './custom_cache',
        'cacheCodes' => ['2xx', '302']
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
    ],
    "middlewares" => [
        [
            'class' => 'Tent\Middlewares\MuCustomCache',
            'location' => "./cache",
            'matchers' => [
                [
                    'class'     => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => ["2xx", "3xx"]
                ]
            ]
        ],
    ],
]);
```

There's no way to make a custom middleware run before the handler's own defaults.

## Expected Behavior

A new, optional `prependMiddlewares` rule key lets custom middlewares run *before* the handler's built-in defaults, while the existing `middlewares` key keeps running its entries *after* those defaults, unchanged. When `prependMiddlewares` is absent, behavior is byte-for-byte identical to today.

## Solution

Add a new top-level rule key, `prependMiddlewares`, sibling to the existing `middlewares` key, whose entries are inserted *before* the handler's default middlewares instead of appended after:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'cache' => './custom_cache',
        'cacheCodes' => ['2xx', '302']
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
    ],
    "prependMiddlewares" => [
        [
            'class' => 'Tent\Middlewares\MuCustomCache',
            'location' => "./cache",
            'matchers' => [
                [
                    'class'     => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => ["2xx", "3xx"]
                ]
            ]
        ],
    ],
    "middlewares" => [
        // still appended AFTER the handler's default middlewares (today's behavior, unchanged)
    ],
]);
```

### Why this shape

Execution order today is 100% array order — there's no priority/interleaving concept, and this issue only needs "before the defaults" vs. "after the defaults" (today's behavior). A per-middleware `position` field or a numeric priority system would cover more ground (e.g. interleaving with *specific* defaults) but nothing currently needs that, so a second parallel key is the smallest change that mirrors the existing config shape.

### Implementation touch points

Two places need to change, not just one:

1. `Rule::build()` (`source/source/lib/models/Rule.php:98-108`) currently only forwards `middlewares` into `handlerParams` (line 101: `$handlerParams['middlewares'] = $params['middlewares'] ?? [];`) — it needs an equivalent line for `prependMiddlewares`, or the new key never reaches the handler at all, since `Configuration::buildRule()` delegates straight to `Rule::build()`.
2. `RequestHandler::build()` (`source/source/lib/request_handlers/RequestHandler.php:107-116`) is the choke point where default middlewares (added during handler construction) meet user-configured ones:

```php
$handler = self::handlerClass($params)::build($params);
$handler->prependMiddlewares($params['prependMiddlewares'] ?? []);
$handler->buildMiddlewares($params['middlewares'] ?? []);
```

`addMiddleware`/`buildMiddlewares` currently only push to the end of `$this->middlewares`, so a new `prependMiddlewares()` method needs to insert at the front instead (e.g. `array_splice`/`array_unshift`), preserving the relative order of multiple `prependMiddlewares` entries among themselves.

### Scope

Implement in the generic base `RequestHandler::build()`, not scoped to `DefaultProxyRequestHandler` alone. `DefaultProxyRequestHandler` is the only handler with built-in defaults today (`ProxyRequestHandler` and `StaticFileHandler` have none), but implementing it once at the base class gives every current and future handler type the option for free, with no behavioral difference for handlers that have no defaults to prepend before.

### Documentation

`docs/guides/tent/defining-rules.md:7` documents the `middlewares` rule key today; it (and any other doc mentioning `middlewares`, e.g. `docs/request-handlers.md`, `docs/creating-middlewares.md`) should be updated to mention `prependMiddlewares` alongside it. In scope for this issue.

### Backward compatibility

Purely additive. `prependMiddlewares` is a new, optional rule key; when absent, behavior is byte-for-byte identical to today.

### Testing strategy

Follow the existing pattern in `RequestHandlerBuildMiddlewareTest`/`DefaultProxyRequestHandlerBuildTest`, which verifies array order indirectly via overlapping `SetHeadersMiddleware` header keys (later entry in the array wins) rather than a spy/counter — no new test infrastructure needed:

1. **Generic ordering** — build a handler with `middlewares => [SetHeadersMiddleware(['X-Test' => 'append'])]` and `prependMiddlewares => [SetHeadersMiddleware(['X-Test' => 'prepend'])]`; assert the final header is `'append'`, proving the prepended middleware runs first and gets overwritten by the appended one.
2. **Interaction with real defaults** (`DefaultProxyRequestHandlerBuildTest`) — configure `prependMiddlewares` to set the same header that `DefaultProxyRequestHandler::initializeMiddlewares()` sets (e.g. `Host`); assert the handler's own default wins, proving the prepended middleware runs before the handler's built-in defaults, not just before `middlewares`.
3. **Backward-compat regression** — a rule with no `prependMiddlewares` key behaves exactly as today.

## Benefits

- Lets custom middlewares (e.g. caching, auth) run ahead of a handler's built-in defaults (like `DefaultProxyRequestHandler`'s header rewriting) when ordering matters.
- Implemented once at the base `RequestHandler` level, so every current and future handler type gains the option automatically.
- Fully backward compatible — no change to existing rule configs.
