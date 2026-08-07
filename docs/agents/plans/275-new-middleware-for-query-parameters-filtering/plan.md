# Plan: New middleware for query parameters filtering

Issue: [275-new-middleware-for-query-parameters-filtering.md](../../issues/275-new-middleware-for-query-parameters-filtering.md)

## Overview

Add `Tent\Middlewares\FilterQueryParamsMiddleware`, a general-purpose middleware that filters the query string of an incoming request against a configured `params` list, in either `allow` (default) or `deny` mode. Wire it as a new opt-in `filter_query_params` build option on `DefaultProxyRequestHandler`, inserted before `FileCacheMiddleware` so cache keys reflect the filtered query. This requires a small supporting addition to `ProcessingRequest` (`setQuery()`), and a documentation update to keep `docs/request-handlers.md` accurate.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

`tent` implements and owns the following contract; `product-dev-extending` documents it as-is in `docs/request-handlers.md`, without altering it:

- **Middleware class**: `Tent\Middlewares\FilterQueryParamsMiddleware`, config keys:
  - `params` (array of strings, optional, defaults to `[]`) — the parameter names to allow/deny.
  - `mode` (string, optional, defaults to `'allow'`) — either `'allow'` or `'deny'`. Any other value throws `\InvalidArgumentException` from `build()`.
- **`DefaultProxyRequestHandler` build option**: `filter_query_params` (array, optional, defaults to `null`/absent).
  - Absent/`null` → `FilterQueryParamsMiddleware` is not added; existing behavior is unchanged.
  - When present, its value (e.g. `['params' => ['id', 'page'], 'mode' => 'allow']`) is passed straight through to `FilterQueryParamsMiddleware::build()`.
  - When present, the middleware is inserted **before** `FileCacheMiddleware` in `initializeMiddlewares()`, so `QueryRequestHasher`'s cache key reflects the already-filtered query string.
- Example config, for use in the doc:
  ```php
  Configuration::buildRule([
      'handler' => [
          'type' => 'default_proxy',
          'host' => 'http://api:80',
          'filter_query_params' => [
              'params' => ['id', 'page'],
              'mode' => 'allow'
          ]
      ],
      'matchers' => [
           ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
      ]
  ]);
  ```
