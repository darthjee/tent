# Reference

## Container paths

| Path inside container          | Purpose                                      |
|--------------------------------|----------------------------------------------|
| `/var/www/html/configuration/` | PHP rule files (mount your config here)      |
| `/var/www/html/static/`        | Static files served by `StaticFileHandler`   |
| `./cache` (default, relative)  | File cache written by `FileCacheMiddleware`  |

## Handlers

| `type`          | Class                          | What it does |
|-----------------|--------------------------------|--------------|
| `default_proxy` | `DefaultProxyRequestHandler`   | Proxy with automatic Host header fix and file cache |
| `proxy`         | `ProxyRequestHandler`          | Bare proxy — no default middlewares |
| `static`        | `StaticFileHandler`            | Serve files from a local directory |

## Middlewares

| Class                                   | What it does |
|-----------------------------------------|--------------|
| `Tent\Middlewares\FileCacheMiddleware`   | Cache upstream responses to disk; serve on subsequent requests |
| `Tent\Middlewares\CacheCleanupMiddleware` | Delete stale cache directories on `POST`/`PATCH`/`PUT`/`DELETE` |
| `Tent\Middlewares\SetHeadersMiddleware` | Set or override request headers before forwarding |
| `Tent\Middlewares\RenameHeaderMiddleware` | Move a header value to a different header name |
| `Tent\Middlewares\SetPathMiddleware`    | Rewrite the request path before the handler runs |
| `Tent\Middlewares\RedirectMiddleware`   | Rewrite path using regex and return a 302 response |

## Cache matchers

| Class                              | What it does |
|------------------------------------|--------------|
| `Tent\Matchers\StatusCodeMatcher`  | Cache only responses matching specified status codes or patterns (`'2xx'`, `200`, `301`) |
| `Tent\Matchers\RequestMethodMatcher` | Cache only requests with specified HTTP methods (`GET`, `HEAD`) |

## Rule matchers

| `type`        | Behavior                                        |
|---------------|-------------------------------------------------|
| `exact`       | Match URI exactly                               |
| `begins_with` | Match URI prefix                                |
| `ends_with`   | Match URI suffix                                |
| `regex`       | Match URI with regular expression pattern       |

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
