# tent Plan: Allow the prepending of middlewares

Main plan: [plan.md](plan.md)

## Shared contracts

Produces the `prependMiddlewares` rule key: same shape as `middlewares`, entries inserted before the handler's default middlewares (built during handler construction), preserving their own relative order. `middlewares` keeps appending after the defaults, unchanged. See [plan.md](plan.md)'s "Shared contracts" for the exact example `product-dev-extending` will document.

## Implementation Steps

### Step 1 — Add prepend support to `RequestHandler`

In `source/source/lib/request_handlers/RequestHandler.php`:

- Add a `prependMiddleware(Middleware $middleware): Middleware` method that inserts at the front of `$this->middlewares` (e.g. `array_unshift`).
- Add a `prependMiddlewares(array $attributes): array` method, mirroring `buildMiddlewares()` (lines 88-94), but building all `Middleware::build($attributes)` instances into a temporary array first, then splicing that whole block onto the front of `$this->middlewares` in one shot (`array_splice($this->middlewares, 0, 0, $built)`) — inserting one at a time via repeated `array_unshift` would reverse the relative order of multiple `prependMiddlewares` entries.

### Step 2 — Wire it into the handler factory

In `RequestHandler::build()` (lines 107-116), call the new method between handler construction and the existing `buildMiddlewares` call:

```php
$handler = self::handlerClass($params)::build($params);
$handler->prependMiddlewares($params['prependMiddlewares'] ?? []);
$handler->buildMiddlewares($params['middlewares'] ?? []);
```

This matters because handler construction is where a handler like `DefaultProxyRequestHandler` adds its own defaults (`initializeMiddlewares()`) — `prependMiddlewares` must run after that point to have defaults to insert in front of, but before `buildMiddlewares` so the append-path is unaffected.

### Step 3 — Forward the key from `Rule::build()`

In `source/source/lib/models/Rule.php:98-108`, `Rule::build()` currently only forwards `middlewares` into `handlerParams` (line 101). Add the equivalent line for `prependMiddlewares`:

```php
$handlerParams['prependMiddlewares'] = $params['prependMiddlewares'] ?? [];
```

Without this, `Configuration::buildRule()` → `Rule::build()` never passes the new key down to `RequestHandler::build()`, even though Step 2 supports it.

### Step 4 — Tests

- `source/tests/unit/lib/request_handlers/RequestHandler/RequestHandlerBuildMiddlewareTest.php`: add a test using two `SetHeadersMiddleware` instances with the same header key — one via `prependMiddlewares`, one via `middlewares` — asserting the `middlewares` one wins (proving prepend runs first and gets overwritten). Add a second test with two `prependMiddlewares` entries setting different headers, asserting both applied and, via a shared header key between them, that the first-listed entry's effect is overwritten by the second (proving relative order among prepended entries is preserved).
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php`: add a test configuring `prependMiddlewares` to set the same header that `DefaultProxyRequestHandler::initializeMiddlewares()` sets (e.g. `Host`), asserting the handler's own default wins — proving the prepended middleware runs before the handler's built-in defaults, not just before `middlewares`.
- `source/tests/unit/lib/models/Rule/RuleBuildTest.php`: add a test asserting a rule built with `prependMiddlewares` actually applies that middleware when the rule's handler processes a request (end-to-end through `Configuration::buildRule`/`Rule::build`), proving the key is wired through and not silently dropped.
- Confirm existing tests in all three files still pass unmodified — the change is additive, so no existing assertions should need updating.

## Files to Change

- `source/source/lib/request_handlers/RequestHandler.php` — add `prependMiddleware`/`prependMiddlewares`, wire into `build()`.
- `source/source/lib/models/Rule.php` — forward `prependMiddlewares` into `handlerParams` in `build()`.
- `source/tests/unit/lib/request_handlers/RequestHandler/RequestHandlerBuildMiddlewareTest.php` — new ordering tests.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — new real-defaults interaction test.
- `source/tests/unit/lib/models/Rule/RuleBuildTest.php` — new end-to-end wiring test.
- `docs/agents/architecture.md` — update the "Configuration Rules Pattern" example and/or `Rule`/`RequestHandler` descriptions to mention `prependMiddlewares` alongside `middlewares`, since this doc is the source-layout reference tied directly to these two files.

## CI Checks

- `source`: `composer tests:unit` (CI job: `unit_test`, runs `composer coverage` which covers `tests/unit`)
- `source`: `composer lint` (CI job: `checks`)

## Notes

- Only `DefaultProxyRequestHandler` has built-in default middlewares today (`ProxyRequestHandler` and `StaticFileHandler` have none), so for those two handlers `prependMiddlewares` behaves identically to `middlewares` (nothing to prepend before). That's expected and doesn't need special-casing — the generic implementation handles it naturally.
- Keep `Middleware::build()` itself untouched — only the insertion point into `$this->middlewares` changes.
