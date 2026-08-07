# product-dev-extending Plan: New middleware for query parameters filtering

Main plan: [plan.md](plan.md)

## Shared contracts

You document, without altering, the contract `tent` implements:

- `Tent\Middlewares\FilterQueryParamsMiddleware`, config keys `params` (array, default `[]`) and `mode` (`'allow'`/`'deny'`, default `'allow'`, invalid values throw `\InvalidArgumentException`).
- `DefaultProxyRequestHandler`'s new `filter_query_params` option (array, default `null`/absent → middleware not added), inserted before `FileCacheMiddleware` in the internal default middleware order.

Wait for `tent`'s implementation to land (or at least stabilize) before finalizing wording, since your job here is to keep the doc truthful against the real code, not to co-design the interface.

## Implementation Steps

### Step 1 — Update the `DefaultProxyRequestHandler` options table

In `docs/request-handlers.md`, under `## DefaultProxyRequestHandler (\`default_proxy\`)`:

- Add a 4th item to the "It automatically adds:" numbered list: `FilterQueryParamsMiddleware(...)` (only when `filter_query_params` is configured), matching the existing conditional phrasing used for `FileCacheMiddleware(...)` ("unless cache is disabled").
- Add a row to the `### Options` table:
  ```
  | `filter_query_params` | `array` | No | — (middleware not added) | Filters incoming query params; see `filter_query_params` example below |
  ```

### Step 2 — Add a usage example

Add a new `### Example: Filter query parameters` subsection (near the existing cache examples), showing:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'filter_query_params' => [
            'params' => ['id', 'page'],
            'mode' => 'allow' // optional, defaults to 'allow'
        ]
    ],
    'matchers' => [
         ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
    ]
]);
```

Briefly note both modes (`allow`/`deny`) and that filtering runs before the cache middleware, so the cache key reflects the filtered query.

### Step 3 — Update "Middleware Order" section if needed

Re-read `## Middleware Order` after `tent`'s implementation lands. If the internal default middleware list there is ever enumerated step-by-step (it currently isn't — it only says "internal default middlewares are created first"), no change is needed. Otherwise, confirm the description still holds with `FilterQueryParamsMiddleware` in the mix (it runs as part of "internal defaults", same bucket as `FileCacheMiddleware`) and adjust only if the existing wording becomes inaccurate.

### Step 4 — Verify (per your standard process)

Follow this doc's own "How to verify" process: wire the example from Step 2 into a throwaway rule, exercise it against a running stack or a PHPUnit test, confirm the documented behavior matches reality, then remove the throwaway code.

## Files to Change

- `docs/request-handlers.md` — options table, automatic-middlewares list, new example, and (if needed) the Middleware Order section.

## CI Checks

None — documentation-only change, no dedicated CI job for `docs/`.

## Notes

- Do not invent config keys or defaults beyond what `tent`'s implementation actually provides — verify against `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` and `source/source/lib/middlewares/FilterQueryParamsMiddleware.php` once they exist.
- If `tent`'s implementation deviates from the shared contract in the main plan (e.g. different default, different exception type), flag it back to `tent` rather than silently documenting the deviation.
