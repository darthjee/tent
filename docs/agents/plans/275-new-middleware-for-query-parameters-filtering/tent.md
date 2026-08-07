# tent Plan: New middleware for query parameters filtering

Main plan: [plan.md](plan.md)

## Shared contracts

You own and implement the full contract described in the main plan's "Shared contracts" section:

- `Tent\Middlewares\FilterQueryParamsMiddleware` with `params`/`mode` config keys, validated in `build()`.
- `DefaultProxyRequestHandler`'s new opt-in `filter_query_params` build option, inserted before `FileCacheMiddleware`.

`product-dev-extending` will document this contract in `docs/request-handlers.md` exactly as you implement it — keep the config key names, defaults, and validation behavior stable once written; flag `product-dev-extending` if anything here needs to change after the doc is written.

## Implementation Steps

### Step 1 — Add `ProcessingRequest::setQuery()`

In `source/source/lib/models/ProcessingRequest.php`, add a `setQuery(string $query): string` method mirroring `setRequestPath()` exactly:

```php
public function setQuery(string $query): string
{
    return $this->query = $query;
}
```

Purely additive; place it near `query()` for readability.

### Step 2 — Create `FilterQueryParamsMiddleware`

Create `source/source/lib/middlewares/FilterQueryParamsMiddleware.php`, extending `Tent\Middlewares\Middleware` (follow `RenameHeaderMiddleware`/`SetPathMiddleware` as the structural pattern, including a docblock with a `Configuration::buildRule()` usage example).

- Constructor: `__construct(array $params = [], string $mode = 'allow')`. Store both as private properties.
- `build(array $attributes): self`:
  - `$params = $attributes['params'] ?? [];`
  - `$mode = $attributes['mode'] ?? 'allow';`
  - If `$mode` is not `'allow'` or `'deny'`, throw `\InvalidArgumentException` with a message naming the invalid value.
  - Return `new self($params, $mode);`
- `processRequest(ProcessingRequest $request): ProcessingRequest`:
  - Read `$request->query()`.
  - If empty, return `$request` unchanged (no-op).
  - `parse_str($request->query(), $parsed);`
  - Filter `$parsed` by top-level key against `$this->params`, using `array_intersect_key`/`array_diff_key` (keyed by `array_flip($this->params)`) depending on `$this->mode` — `allow` keeps only listed keys, `deny` removes listed keys. `array_intersect_key`/`array_diff_key` preserve the original key order of `$parsed` (which itself follows `parse_str`'s left-to-right parse order of the original query string), satisfying the order-preservation requirement without extra sorting.
  - `$request->setQuery(http_build_query($parsed));`
  - Return `$request`.
- No `processResponse()` override needed (defaults to no-op in the base class).

### Step 3 — Wire into `DefaultProxyRequestHandler`

In `source/source/lib/request_handlers/DefaultProxyRequestHandler.php`:

- Add `use Tent\Middlewares\FilterQueryParamsMiddleware;`.
- Add a `private ?array $filterQueryParams;` property, constructor parameter (default `null`), and assignment, following the existing `skipCacheHeader`/`requestHasher` pattern.
- In `build()`, read `$filterQueryParams = $params['filter_query_params'] ?? null;` and pass it through to the constructor.
- In `initializeMiddlewares()`, right before the `if ($this->cache !== false)` block, add:
  ```php
  if ($this->filterQueryParams !== null) {
      $this->addMiddleware(FilterQueryParamsMiddleware::build($this->filterQueryParams));
  }
  ```
  This guarantees insertion order: `RenameHeaderMiddleware` → `SetHeadersMiddleware` → `FilterQueryParamsMiddleware` (if configured) → `FileCacheMiddleware` (if enabled).
- Update the class docblock: add a short "4. FilterQueryParamsMiddleware (optional)" line to the numbered list, and optionally a new `@example` block showing `filter_query_params` usage (keep it consistent with the existing cache examples).

### Step 4 — Tests

Add, following the existing per-class test folder convention:

- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareTest.php`:
  - `processRequest()`: allow mode (keeps only listed params), deny mode (strips listed params), empty query (no-op), empty `params` list in both modes, duplicate scalar keys (`?a=1&a=2` collapses per `parse_str`), array-style keys (`?a[]=1&a[]=2`, `?a[b]=1`) matched by top-level key only, params in the list absent from the query (no effect), order preservation of surviving params.
- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareBuildTest.php`:
  - `params` defaults to `[]` when omitted.
  - `mode` defaults to `'allow'` when omitted.
  - `mode` accepts `'allow'`/`'deny'` explicitly.
  - An invalid `mode` value throws `\InvalidArgumentException`.
- Extend `source/tests/unit/lib/models/ProcessingRequest/...` (find the existing test file covering `setRequestPath()`/`requestPath()` and mirror it) with coverage for `setQuery()`/`query()`.
- Extend `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` for the new `filter_query_params` option (absent → not passed to constructor / no middleware; present → passed through).
- Extend or add a scenario test alongside `DefaultProxyRequestHandlerCachedTest.php` confirming: (a) `FilterQueryParamsMiddleware` is absent from the middleware stack when `filter_query_params` is omitted, (b) when present, it runs before `FileCacheMiddleware` — e.g. assert the cache key/hash reflects the filtered query string for a request whose non-listed params vary.

## Files to Change

- `source/source/lib/models/ProcessingRequest.php` — add `setQuery()`.
- `source/source/lib/middlewares/FilterQueryParamsMiddleware.php` — new middleware.
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — new `filter_query_params` option, wired before `FileCacheMiddleware`.
- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareTest.php` — new.
- `source/tests/unit/lib/middlewares/FilterQueryParamsMiddleware/FilterQueryParamsMiddlewareBuildTest.php` — new.
- `source/tests/unit/lib/models/ProcessingRequest/...` — extend for `setQuery()`.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — extend.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerCachedTest.php` (or a sibling scenario test file) — extend/add.

## CI Checks

- `source`: `docker compose run --rm tent_tests composer tests:unit` (CI job: `unit_test`, runs `composer coverage`)
- `source`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)

## Notes

- `array_intersect_key`/`array_diff_key` in PHP preserve the key order of the first argument, so filtering `$parsed` (in its `parse_str`-derived order) against `array_flip($this->params)` naturally satisfies the "preserve original order" edge case — no extra sorting step needed. Verify this assumption with a dedicated order-preservation test case in Step 4.
- `http_build_query()`'s default encoding (RFC1738, `+` for spaces) is acceptable per the issue's Edge Cases section — encoding is not preserved byte-for-byte from the original query string.
- Keep the new `filter_query_params` config key and its passthrough shape frozen once `product-dev-extending` documents it in Step 1 of their plan — coordinate directly if a change becomes necessary during implementation.
