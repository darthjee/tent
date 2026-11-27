# Reference

## Container paths

| Path inside container          | Purpose                                      |
|--------------------------------|----------------------------------------------|
| `/var/www/html/configuration/` | PHP rule files (mount your config here)      |
| `/var/www/html/static/`        | Static files served by `StaticFileHandler`   |
| `./cache` (default, relative)  | File cache written by `FileCacheMiddleware`  |
| `/etc/apache2/tent.d/`         | Apache [access-log](#access-log) overrides (`*.conf`; mount a file or a directory). Always wins over `TENT_ACCESS_LOG_FORMAT` |

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
| `Tent\Middlewares\FilterQueryParamsMiddleware` | Allow-list or deny-list query parameters before forwarding |
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

## Access log

Apache writes one access-log line per request to the container's stdout (`docker logs`). The settings below are available in the `darthjee/tent` image from **1.4.0**. They configure the image, so they are not `configuration/` rules.

The access log is a separate stream from Tent's own log messages: `LOG_LEVEL` does not affect it. From 1.4.0, the `debug` message about an upstream response no longer includes the query string.

### `TENT_ACCESS_LOG_FORMAT`

This environment variable selects a named preset. It does not accept a format string; use a [drop-in file](#drop-in-overrides) for a custom format.

| Value | Access-log line |
|-------|-----------------|
| `safe` (default, also used when unset or empty) | No query string, no `Referer`, includes the request duration |
| `combined` | The legacy Debian line, with the query string and the `Referer` |

Any other value (values are case-sensitive) stops the container at startup, with exit code `1` and this message on stderr:

```text
TENT_ACCESS_LOG_FORMAT: invalid preset '<value>' (accepted: safe, combined)
```

### Safe format

```apache
LogFormat "%h %l %u %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" combined
```

The `safe` preset redefines Apache's `combined` format name with the line above, and `vhost_combined` with `%v:%p ` followed by the same string. Compared with the Debian format:

- `%m %U %H` (method, path, protocol) replaces `%r` (the full request line), which drops the query string.
- The `Referer` header is removed.
- `%D`, the time taken to serve the request in microseconds, is added before the user agent.

Each preset logs the same request, `GET /probe?token=SMOKE` with `Referer: http://x/?ref=LEAK`, as follows:

```text
# safe
127.0.0.1 - - [<time>] "GET /probe HTTP/1.1" 404 472 1040 "curl/8.14.1"

# combined
127.0.0.1 - - [<time>] "GET /probe?token=SMOKE HTTP/1.1" 404 472 "http://x/?ref=LEAK" "curl/8.14.1"
```

### Drop-in overrides

Tent loads every `*.conf` file in `/etc/apache2/tent.d/` after the presets, so **a drop-in always wins** over `TENT_ACCESS_LOG_FORMAT`.

To change the format, redefine the `combined` and `vhost_combined` format names. The image's virtual host already logs through `combined`, so declaring a new format name with another global `CustomLog` does not replace its line.

For example, this `my-log.conf` removes the client address from the `safe` line:

```apache
LogFormat "%t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" combined
LogFormat "%v:%p %t \"%m %U %H\" %>s %O %D \"%{User-Agent}i\"" vhost_combined
```

Mount it with `-v ./my-log.conf:/etc/apache2/tent.d/my-log.conf:ro` (or the same entry under `volumes:` in Docker Compose). You can also mount a whole directory of `*.conf` files onto `/etc/apache2/tent.d/`.

- Files without the `.conf` suffix are ignored.
- A drop-in is regular Apache configuration, so a syntax error in it stops the container at startup.
- An invalid `TENT_ACCESS_LOG_FORMAT` value stops the container even when a drop-in is mounted.

### What can still be logged

The `safe` preset does not remove every sensitive value:

- **Tokens in the path** (for example `/reset/<token>`) are still logged. If your routes carry secrets, use a drop-in format that leaves the path (`%U`) out.
- **Client address** (`%h`) is personal data. When Tent sits behind a reverse proxy, it is the proxy's address, not the end user's.
- **HTTP Basic user name** (`%u`): when a request sends `Authorization: Basic`, its user name is logged, although Apache authentication is not configured. The password is not logged. If your clients send an API key as the user name, use a drop-in format without `%u`.
- **Apache error log** (stderr): neither the presets nor `LogFormat` change it. Apache appends `referer: <value>`, query string included, to the error-log lines written while serving a request that sent that header. This includes the messages Tent writes at any `LOG_LEVEL`.

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
