# Reference

## Container paths

| Path inside container          | Purpose                                      |
|--------------------------------|----------------------------------------------|
| `/var/www/html/configuration/` | PHP rule files (mount your config here)      |
| `/var/www/html/static/`        | Static files served by `StaticFileHandler`   |
| `./cache` (default, relative)  | File cache written by `FileCacheMiddleware`  |
| `/etc/apache2/tent.d/`         | Apache overrides (`*.conf`; mount a file or a directory) for the [access log](#access-log) and the [PHP log level](#overriding-the-defaults). Always wins over `TENT_ACCESS_LOG_FORMAT` |
| `/usr/local/etc/php/conf.d/tent.ini` | Production PHP settings shipped by the `darthjee/tent` image. Do not mount over it; add a [`zz-*.ini` file](#overriding-the-defaults) |
| `/etc/apache2/conf-enabled/00-tent-0-php-log-level.conf` | Log level shipped by the `darthjee/tent` image (`LogLevel warn php:info`). Override it through `/etc/apache2/tent.d/` |

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
- **Apache error log** (stderr): neither the presets nor `LogFormat` change it. Apache appends `referer: <value>`, query string included, to the error-log lines written while serving a request that sent that header. This includes the [PHP diagnostics](#php-error-output) and the messages Tent writes at any `LOG_LEVEL`.

## PHP error output

The `darthjee/tent` image sets `display_errors = Off` and `log_errors = On` in `/usr/local/etc/php/conf.d/tent.ini`. A PHP warning, notice or deprecation is written to the log, so it does not change the status, the headers or the body of the response.

The development and test images (`darthjee/dev_tent` and `darthjee/tent-test`) do not use these settings. They keep `display_errors` on, so a diagnostic that is only logged in production is printed in their responses and command output.

`display_errors` covers PHP diagnostics only. Output printed by your own files, such as a stray `echo`, or a byte order mark or blank line before `<?php`, still reaches the client before the headers and corrupts the response. A code-level guard against it is planned in [issue #335](https://github.com/darthjee/tent/issues/335).

### Where diagnostics go

Diagnostics go to the container's stderr (`docker logs` / `docker compose logs`), in the Apache error log. This is the same stream as Tent's own log messages.

| Prefix | What is logged with it |
|--------|------------------------|
| `[php:error]` (or higher) | Fatal errors, including uncaught exceptions |
| `[php:warn]` | Warnings |
| `[php:notice]` | Notices, and every message from Tent's `Logger` |
| `[php:info]` | Deprecations |

One example of each, plus a Tent `Logger` line (the third one). `<time>`, `<pid>`, `<tid>`, `<address>` and `<port>` replace values which change on every run:

```text
[<time>] [php:warn] [pid <pid>:tid <tid>] [client <address>:<port>] PHP Warning:  Undefined array key "user" in /var/www/html/extension/ExampleMiddleware.php on line 23
[<time>] [php:notice] [pid <pid>:tid <tid>] [client <address>:<port>] PHP Notice:  Only variables should be passed by reference in /var/www/html/extension/ExampleMiddleware.php on line 24
[<time>] [php:notice] [pid <pid>:tid <tid>] [client <address>:<port>] [DEBUG] [404] - upstream response \xe2\x80\x94 method: GET, uri: /users, upstream: http://api:80/users
[<time>] [php:info] [pid <pid>:tid <tid>] [client <address>:<port>] PHP Deprecated:  strlen(): Passing null to parameter #1 ($string) of type string is deprecated in /var/www/html/extension/ExampleMiddleware.php on line 25
[<time>] [php:error] [pid <pid>:tid <tid>] [client <address>:<port>] PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 1073745920 bytes) in /var/www/html/extension/ExampleMiddleware.php on line 16
```

- Deprecations are included because the image sets `LogLevel warn php:info`. With Apache's `LogLevel warn` alone, they are dropped.
- Tent's `Logger` lines use `[php:notice]` whatever their own level is. That level (`[DEBUG]`, `[INFO]`, `[WARN]` or `[ERROR]`) is part of the message. `LOG_LEVEL` filters these lines only; it does not filter PHP diagnostics.
- Apache escapes non-ASCII characters and line breaks. In the example, `\xe2\x80\x94` is a dash. The stack trace of an uncaught exception stays on one line, with `\n` between its parts.
- On a fatal error, the client receives a `500` with an empty body, if nothing was sent before the error.

Command-line PHP inside the image writes its diagnostics to stderr, and the script output stays on stdout. With `docker run`, that stderr is part of the container's logs. With `docker exec` (or `docker compose exec`), it goes to your terminal session and not to `docker logs`.

### Overriding the defaults

**PHP settings.** Mount a file named `/usr/local/etc/php/conf.d/zz-<name>.ini`. PHP reads it after `tent.ini`, so the settings in your file win and all the others keep the image values. A file whose name sorts before `tent.ini`, such as `custom.ini`, cannot override what `tent.ini` sets.

For example, this `zz-local.ini` prints diagnostics in the response while you debug:

```ini
display_errors = On
```

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    volumes:
      - ./proxy/php/zz-local.ini:/usr/local/etc/php/conf.d/zz-local.ini:ro
```

Do not use it in production: a diagnostic then turns the response into a `200` without its headers, with the message in the body.

> **Warning:** do not bind-mount a file over `/usr/local/etc/php/conf.d/tent.ini`. It replaces the whole production configuration and silently turns `display_errors` back on. It also turns `log_errors` off, so PHP diagnostics no longer reach stderr, and the upload limits return to PHP's defaults unless your file sets them. Use a `zz-<name>.ini` file instead.

**Log level.** Mount a `*.conf` file with a `LogLevel` directive in `/etc/apache2/tent.d/`, the same folder used for the access-log [drop-in files](#drop-in-overrides). It is read after the image's level. For example, this `log-level.conf` stops logging deprecations:

```apache
LogLevel warn
```

Mount it with `-v ./log-level.conf:/etc/apache2/tent.d/log-level.conf:ro`.

- A level without a module name, as above, also replaces the image's `php:info`. To change the general level and keep deprecations, repeat it: `LogLevel <level> php:info`.
- Without `php:info`, a level above `warn`, such as `error`, also hides PHP warnings. It does not hide `[php:notice]` lines, which Apache always writes to this log. Use `LOG_LEVEL` to reduce Tent's own messages.

### Log access and rotation

- **Restrict access to the logs.** Diagnostics may include server paths and request data: error messages, upstream URLs, the client address, the `Referer` header (query string included) and, in the stack trace of an uncaught exception, argument values.
- **Configure log rotation.** Docker's default `json-file` logging driver does not rotate logs. A client which can trigger a warning on every request produces at least one log line per request, which can fill the host disk.

With `docker run`:

```bash
docker run --log-driver json-file --log-opt max-size=10m --log-opt max-file=3 darthjee/tent:latest
```

With Docker Compose:

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"
```

Both limits apply to all the container's output: the access log on stdout and the error log on stderr.

### Source-tarball deployments

A deployment from `tent-<tag>-source.tar.gz`, without Docker, does not get the image's configuration. Set these values in your PHP configuration:

```ini
display_errors = Off
log_errors = On
```

Also use an error log level which keeps deprecations. Under Apache with `mod_php`:

```apache
LogLevel warn php:info
```

A code-level protection which does not depend on these settings is planned in [issue #335](https://github.com/darthjee/tent/issues/335).

### Upgrade note

When you upgrade a `darthjee/tent` image which did not have these settings:

- **Responses are no longer corrupted by PHP diagnostics.** A request which raised a warning now returns the real upstream status and headers (before, it returned a `200` with the headers dropped and the warning in the body). A request which hits a fatal error now returns a `500` with an empty body (before, a `200` with the error text).
- **New lines on stderr.** PHP diagnostics now appear as `[php:*]` lines (`[php:error]`, `[php:warn]`, `[php:notice]` and, for deprecations, `[php:info]`). They may trigger log alerts. Tent's own `[php:notice]` lines were already there. Configure log rotation.
- **Do not bind-mount over `tent.ini`.** If you mount your own `/usr/local/etc/php/conf.d/tent.ini`, for example to change the upload limits, move your settings to a `zz-<name>.ini` file in the same folder. Otherwise `display_errors` stays on.
- **Command-line diagnostics moved from stdout to stderr.**
- **The development and test images are unchanged.**

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
