# Runtime Flow

## Entry Point

All HTTP requests hit Apache first. The `.htaccess` rewrite rule forwards every request to `source/source/index.php`, which bootstraps the application (loads `loader.php`, reads configuration) and delegates to `RequestProcessor`.

## Request Lifecycle

```
HTTP Request
→ Apache (.htaccess rewrite)
→ source/source/index.php
→ RequestProcessor  (iterates Rule objects, picks first match)
→ Middleware chain  (processRequest — forward order)
→ RequestHandler   (proxy / static file / 404)
→ Middleware chain  (processResponse — forward order)
→ HTTP Response
```

## Step-by-Step

### 1. Apache rewrite

`.htaccess` rewrites all incoming paths to `index.php`, preserving the original URI in the request.

### 2. Bootstrap (`index.php`)

- Includes `loader.php` (registers all classes via `require_once`)
- Loads user-defined rules from `docker_volumes/configuration/`
- Instantiates `RequestProcessor` and calls `process()`

### 3. Rule matching (`RequestProcessor`)

Iterates through all registered `Rule` objects in order. The **first** rule whose matchers all pass wins. If no rule matches, `MissingRequestHandler` returns a `404`.

### 4. Pre-request middleware chain

Each middleware's `processRequest(ProcessingRequest)` is called in forward order (first-defined runs first). A middleware may:

- Modify request headers or path
- **Short-circuit** by calling `$request->setResponse(...)`, which skips the handler and remaining `processRequest` calls

### 5. Handler execution

The matched handler runs (unless short-circuited):

| Handler | Behaviour |
|---------|-----------|
| `DefaultProxyRequestHandler` | Forwards request to backend, auto-manages Host headers and cache |
| `ProxyRequestHandler` | Forwards request as-is with only explicitly configured middlewares |
| `StaticFileHandler` | Reads a file from disk and returns it |
| `MissingRequestHandler` | Returns 404 immediately |

### 6. Post-response middleware chain

Each middleware's `processResponse(Response)` is called in forward order. A middleware may:

- Add or modify response headers
- Write the response to the file cache

### 7. HTTP response

The final `Response` object is serialised and sent to the client.

---

## Short-Circuit Detail

`FileCacheMiddleware` demonstrates both short-circuit patterns:

- **`processRequest`**: if a cached file exists for the request URI, it loads it and calls `$request->setResponse(...)` — the backend is never contacted.
- **`processResponse`**: if the response passes all configured matchers (e.g., status 200, method GET), the response body is written to disk for future requests.

---

## Middleware Execution Order Note

For `DefaultProxyRequestHandler`, built-in middlewares (`RenameHeaderMiddleware`, `SetHeadersMiddleware`, `FileCacheMiddleware`) are constructed inside the handler and run **before** any rule-level middlewares. Rule-level middlewares are appended and run after the built-ins.
