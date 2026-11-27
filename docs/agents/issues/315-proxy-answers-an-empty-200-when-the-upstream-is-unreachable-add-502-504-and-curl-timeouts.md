# Issue: Proxy answers an empty 200 when the upstream is unreachable; add 502/504 and curl timeouts

## Description

When the upstream is unreachable or hangs, Tent answers the client with an empty `200 OK` (or stalls until PHP/Apache limits). The proxy should instead answer `502 Bad Gateway` on transport failures and `504 Gateway Timeout` on cURL timeouts, with configurable `connect_timeout` / `timeout` on the `proxy` and `default_proxy` handlers.

Affected module: the HTTP layer under `source/source/lib/http/` and the proxy handlers under `source/source/lib/request_handlers/`, with a one-line guard in `source/source/lib/service/ResponseSender.php` and one accessor on `source/source/lib/models/Response.php`.

Reference documentation: `docs/agents/architecture.md` (component map, `require_once` rule for `loader.php`); `docs/agents/flow.md` (request lifecycle, where `processResponse()` runs); `docs/request-handlers.md` (option tables of `default_proxy` and `proxy`, which must gain the two timeout keys).

Downstream need: a consumer wants to synthesize a fixed identity body on its health probes when the upstream is down, keyed on a `5xx` from Tent. Today that case surfaces as an empty `200`.

## Problem

When the upstream is unreachable, `ProxyRequestHandler` answers the client with an **empty `200 OK`**.

`CurlHttpExecutor\Base::executeCurlRequest()` never checks `curl_exec()` for failure. When the connection fails (DNS failure, connection refused, reset), `curl_exec()` returns `false`, `CURLINFO_HTTP_CODE` is `0`, and the body becomes `""`. The `Response` is built with `httpCode = 0`. `ResponseSender::send()` then calls `http_response_code(0)`, which PHP ignores, so Apache sends its default `200`.

Reproduced on `darthjee/tent:1.0.3` (PHP 8.4.26) by running the proxy with the configured backend host absent:

```text
$ curl -si http://localhost/health.json
HTTP/1.1 200 OK
Server: Apache/2.4.68 (Debian)
X-Powered-By: PHP/8.4.26
Content-Length: 0
Content-Type: text/html; charset=UTF-8
```

A second problem: `initCurlRequest()` sets neither `CURLOPT_CONNECTTIMEOUT` nor `CURLOPT_TIMEOUT`. An upstream that accepts the connection and then hangs stalls the request until PHP's or Apache's own limit, and Tent never answers `504`.

Consequences:

- A client, load balancer or health probe sees a successful response from a proxy whose upstream is down.
- A response middleware cannot tell "upstream failed" from a real answer by status code. `httpCode()` is `0`, not a 5xx, so `StatusCodeMatcher` rules for `5xx` never fire.
- No log line records the transport cause. The debug entry reads `[0] - upstream response` and `curl_error()` is discarded.

### Current behaviour (reference)

| Location | Behaviour today |
| --- | --- |
| `source/source/lib/http/CurlHttpExecutor/Base.php` — `executeCurlRequest()` | Calls `curl_exec()` and reads `CURLINFO_HEADER_SIZE` and `CURLINFO_HTTP_CODE`. Never checks the `false` return, never calls `curl_errno()`. A connection failure produces `['body' => '', 'httpCode' => 0, 'headers' => []]`. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/http/CurlHttpExecutor/Base.php) |
| `source/source/lib/http/CurlHttpExecutor/Base.php` — `initCurlRequest()` | Sets only `CURLOPT_RETURNTRANSFER`, `CURLOPT_HEADER` and `CURLOPT_HTTPHEADER`. No `CURLOPT_CONNECTTIMEOUT`, no `CURLOPT_TIMEOUT`. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/http/CurlHttpExecutor/Base.php) |
| `source/source/lib/http/CurlHttpExecutor/Get.php` | Declares no `addExtraCurlOptions()` override; the superclass no-op runs, so a GET carries no timeout configuration at all. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/http/CurlHttpExecutor/Get.php) |
| `source/source/lib/http/CurlHttpExecutor/Post.php` — `addExtraCurlOptions()` | Adds `CURLOPT_POST` and `CURLOPT_POSTFIELDS` only. No timeout options. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/http/CurlHttpExecutor/Post.php) |
| `source/source/lib/http/CurlHttpClient.php` — `request()` | Selects the executor class by HTTP verb and constructs it with `url`, `headers`, `body`, `uploadedFiles`, `postFields`. The client holds no configuration of its own. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/http/CurlHttpClient.php) |
| `source/source/lib/request_handlers/ProxyRequestHandler.php` — `processsRequest()` | Builds `new Response($response)` from the client result without inspecting `httpCode`. A `0` propagates untouched, then logs `[0] - upstream response — method: ..., uri: ..., upstream: ...`. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/request_handlers/ProxyRequestHandler.php) |
| `source/source/lib/service/ResponseSender.php` — `send()` | Calls the status emitter with `$response->httpCode()` unconditionally. `http_response_code(0)` is rejected by PHP, so Apache falls back to its default `200`. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/service/ResponseSender.php) |
| `source/source/lib/models/Response.php` | Exposes `httpCode()` and `isSuccessful()` (`>= 200 && < 300`). No way to state that the response represents an upstream transport failure. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/models/Response.php) |
| `source/source/lib/request_handlers/RequestHandler.php` — `handleRequest()` | `applyResponseMiddlewares()` runs every middleware's `processResponse()` over whatever the handler returned, so a synthesized failure response crosses the full chain. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/request_handlers/RequestHandler.php) |
| `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — `initializeMiddlewares()` | Registers `FileCacheMiddleware` with `new StatusCodeMatcher(['2xx'])` by default, so a `0` is not cached — but it is also not a 5xx and nothing observes the failure. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/request_handlers/DefaultProxyRequestHandler.php) |
| `source/source/lib/utils/HttpCodeMatcher.php` — `match()` | Already supports wildcard patterns such as `5xx` through `regExp()`. Rules can be written for 5xx today; they simply never match, because the status is `0`. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/utils/HttpCodeMatcher.php) |
| `source/source/lib/middlewares/FileCacheMiddleware.php` — `processResponse()` | Writes to disk only when `isCacheable()` passes its configured matchers. A non-2xx synthesized response is therefore already excluded from the cache. [source](https://raw.githubusercontent.com/darthjee/tent/main/source/source/lib/middlewares/FileCacheMiddleware.php) |

## Expected Behavior

- A connection failure whose errno is not a timeout produces a `502 Bad Gateway` response.
- A timeout with `CURLE_OPERATION_TIMEDOUT` produces a `504 Gateway Timeout` response.
- A degenerate result with `errno === 0` and `httpCode === 0` produces `502`, never `200`.
- An injected `httpClient` is wrapped by the normalizer too.
- The synthesized response has an empty body and no headers inherited from the failed cURL transfer.
- The cURL error message is logged through `Logger::warn()` (without the query string) and never appears in the response body or in a response header.
- `http_response_code()` is never called with `0`. `ResponseSender` clamps any value outside `100..599` to `500`.
- `connect_timeout` and `timeout` are accepted on both `default_proxy` and `proxy` handler params, defaulting to `5` and `30` seconds; a negative or non-integer value makes `build()` throw `InvalidArgumentException`.
- Both values reach `curl_setopt()` as `CURLOPT_CONNECTTIMEOUT` and `CURLOPT_TIMEOUT` on every verb.
- The synthesized `502` / `504` crosses the normal response middleware chain, so `StatusCodeMatcher` with `5xx` fires.
- A synthesized failure is never cached: it is not 2xx, so the existing cache rules already exclude it.
- `Response::isTimeout()` is `true` exactly when `httpCode() === 504`.

## Solution

The HTTP layer is free to be redesigned. Normalization lives in a new decorator between the executor and the handler; the executor is responsible only for reporting what cURL said.

### 1. Executor reports the transport error

`source/source/lib/http/CurlHttpExecutor/Base.php`

```php
/**
 * @return array{
 *   body: string,
 *   httpCode: int,
 *   headers: string[],
 *   errno: int,
 *   error: string
 * }
 */
protected function executeCurlRequest(): array
```

`executeCurlRequest()` captures `curl_errno($this->curlHandle)` and `curl_error($this->curlHandle)` before `curl_close()`, and returns them alongside the existing keys. `errno` is `0` and `error` is `''` on success.

### 2. Timeouts are configurable

`source/source/lib/http/CurlHttpExecutor/Base.php`

```php
public function __construct(array $options)
// keys: url, headers, body, uploadedFiles, postFields,
//       connect_timeout (int, default 5), timeout (int, default 30)

protected function initCurlRequest(): void
{
    // ...
    curl_setopt($this->curlHandle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
    curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, $this->timeout);
}
```

`source/source/lib/http/CurlHttpClient.php`

```php
public function __construct(array $options = [])
// keys: connect_timeout, timeout — forwarded to every executor instance
```

`CurlHttpClient::request()` passes the two values into the executor options array next to `url`, `headers`, `body`, `uploadedFiles` and `postFields`.

`source/source/lib/request_handlers/ProxyRequestHandler.php`

```php
public function __construct(
    string $host,
    ?HttpClientInterface $httpClient = null,
    int $connectTimeout = 5,
    int $timeout = 30
)

public static function build(array $params): self
// reads 'host', 'connect_timeout' (default 5), 'timeout' (default 30)
```

The handler **always** wraps its client in the normalizer: `$this->httpClient = new UpstreamFailureNormalizer($httpClient ?? new CurlHttpClient(['connect_timeout' => $connectTimeout, 'timeout' => $timeout]))`. An injected client is therefore normalized too; the timeout arguments only configure the default `CurlHttpClient` and are ignored when a client is injected.

`build()` throws `InvalidArgumentException` when `connect_timeout` or `timeout` is present and is not a non-negative integer. `0` is accepted.

`source/source/lib/request_handlers/DefaultProxyRequestHandler.php`

```php
public static function build(array $params): self
// additionally reads 'connect_timeout' and 'timeout' and forwards them to parent
```

### 3. The normalizer between executor and handler

New class `Tent\Http\UpstreamFailureNormalizer` implementing `HttpClientInterface`.

```php
namespace Tent\Http;

class UpstreamFailureNormalizer implements HttpClientInterface
{
    public function __construct(HttpClientInterface $client) {}

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        array $uploadedFiles = [],
        array $postFields = []
    ): array {}
}
```

### 4. Response knows it timed out

`source/source/lib/models/Response.php`

```php
public function isTimeout(): bool
{
    return $this->httpCode === 504;
}
```

### Semantics

1. The normalizer calls the decorated client and inspects `errno` and `httpCode` from the result array. A missing `errno` key (e.g. a custom injected client) is treated as `0`.
2. `errno === CURLE_OPERATION_TIMEDOUT` produces `['body' => '', 'httpCode' => 504, 'headers' => []]`.
3. Any other non-zero `errno` produces `['body' => '', 'httpCode' => 502, 'headers' => []]`.
4. `errno === 0` with a non-zero `httpCode` returns the decorated client's result unchanged, with the `errno` and `error` keys stripped.
5. `errno === 0` with `httpCode === 0` (the degenerate case produced when `curl_exec()` returned `false` without an errno, or by a custom client that reports no status) maps to `502`, never to a success. Detection is by `httpCode === 0`; the executor adds no extra flag.
6. Response headers received from cURL are discarded on a synthesized failure. Only the status survives.
7. The body of a synthesized failure is always the empty string. Neither the cURL error message nor the upstream host ever reaches the client.
8. The cURL error text and the errno go to `Logger::warn()`, in the existing `[status] - reason` style, with the upstream URL **stripped of its query string** (the query may carry credentials and is never logged, matching the existing rule in `ProxyRequestHandler`). Example: `[502] - upstream unreachable — method: GET, url: http://api:80/health.json, errno: 7, error: Failed to connect to api port 80`.
9. `CURLE_OPERATION_TIMEDOUT` covers both `CURLOPT_CONNECTTIMEOUT` and `CURLOPT_TIMEOUT`. cURL reports the same errno for both, so both map to `504`.
10. No retry, no backoff and no circuit breaker are introduced. One failure produces one synthesized response.

### Lifecycle

The synthesized `Response` is created inside `ProxyRequestHandler::processsRequest()` and returned as a normal response. It is therefore handled by `RequestHandler::applyResponseMiddlewares()` like any other response: `StatusCodeMatcher` with `5xx` matches, response-side middlewares run, and `FileCacheMiddleware` sees a non-2xx code and declines to cache it.

### Failure detection

The signals are the transport `errno` reported by cURL and an `httpCode` of `0`. No inference is made from the body, the headers or the elapsed time.

### Response sending

`source/source/lib/service/ResponseSender.php`

```php
$code = $response->httpCode();
if ($code < 100 || $code > 599) {
    $code = 500;
}
($this->statusEmitter)($code);
```

This is defense in depth. The normalizer must never produce `0`, and this guard keeps `http_response_code()` from ever receiving an invalid value if another handler or a middleware sets one.

### Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Where normalization lives | A new decorator, `Tent\Http\UpstreamFailureNormalizer`, between executor and handler | Keeps the executor honest about what cURL returned and keeps the handler free of transport branching; the decorator is the single testable seam. |
| Injected clients | Always wrapped by the normalizer | A custom `HttpClientInterface` returning `httpCode 0` must not produce an empty `200` either. |
| Degenerate detection | `httpCode === 0` maps to `502` | The normalizer only sees the result array; `0` is never a valid upstream status. No extra executor flag needed. |
| Timeout validation | `build()` throws `InvalidArgumentException` unless the value is a non-negative integer | Misconfiguration fails loudly at boot instead of being silently coerced. |
| Executor contract | `executeCurlRequest()` gains `errno` and `error` keys | The normalizer cannot classify a failure it cannot see. The keys are internal to the HTTP layer and stripped before the handler receives the result. |
| Timeout plumbing | Handler params → `ProxyRequestHandler::build()` → `new CurlHttpClient([...])` → executor options | Follows the existing option flow that already reaches `CurlHttpClient` and the executors. No global state, no env dependency. |
| Timeout names and defaults | `connect_timeout` = 5, `timeout` = 30 | Matches the names and defaults proposed in the issue; both are sane for an internal proxy hop. |
| Timeout classification | Only `CURLE_OPERATION_TIMEDOUT` maps to `504` | cURL reports connect and total timeouts under the same errno; distinguishing further would require timing math and adds no client-visible value. |
| Everything else | Any non-zero errno maps to `502` | DNS failure, refused connection, reset and partial transfer are all "the gateway could not complete the request". |
| Frozen surface | Nothing | The HTTP layer is explicitly in scope for redesign; only the external HTTP contract of the proxy is fixed. |
| Response semantic accessor | `Response::isTimeout()` derived from `httpCode === 504` | Consumers should not compare magic numbers. A `504` received from a real upstream also returns `true` — intentional. |
| Response body | Empty, headers discarded | The cURL error and upstream host must not leak to the caller, and a fixed body would invent content the proxy does not know. |
| Cache interaction | No change to `FileCacheMiddleware` | A `502` / `504` is already outside the `2xx` default; no cache code needs to change. |
| Observability | `Logger::warn()` with method, query-less upstream URL, `errno` and `curl_error()` | The existing debug line reads `[0]` today and carries no cause. Warn level because a failed upstream is an operational event. The query string is never logged. |
| Normalizer test boundary | Unit tests on the normalizer only | The decorator is the only place where the mapping exists; testing it through the handler would duplicate coverage. |

#### Edge cases (pinned)

- `errno === 0` with a non-empty response: returned untouched, `errno` and `error` keys removed.
- `errno === CURLE_OPERATION_TIMEDOUT`: `504`, empty body, no headers.
- Any other non-zero `errno`, including `CURLE_COULDNT_RESOLVE_HOST` and `CURLE_COULDNT_CONNECT`: `502`.
- `curl_exec()` returns `false` with `errno === 0` (`httpCode === 0`): `502`.
- Injected custom client returning no `errno` key and `httpCode === 0`: `502`.
- Injected custom client returning a normal result without `errno`/`error` keys: passed through unchanged.
- Upstream returns a real `502` or `504` over a successful transfer: passed through unchanged, headers and body included, and `Response::isTimeout()` is `true` for the real `504`. Only transport-level failures are synthesized.
- Timeout params omitted on the handler: `5` and `30` apply.
- Timeout params set to `0`: passed to cURL as-is, which disables that timeout.
- Timeout params negative, non-integer or non-numeric (e.g. `-1`, `'abc'`, `2.5`): `build()` throws `InvalidArgumentException`.
- A middleware rewriting the status before sending: the rewritten status wins; `isTimeout()` reflects the final `httpCode`.
- `ResponseSender` receives `0` from any other source: emits `500`, never `0`.
- `default_proxy` with `cacheCodes => ['5xx']`: a synthesized `504` becomes cacheable by explicit configuration. This is the operator's stated intent and is left untouched.
- `CacheStalenessMiddleware` background refresh hitting an unreachable upstream: the refresh writes nothing, since the normalized result is not 2xx for the default configuration.

### Implementation notes

- New file `source/source/lib/http/UpstreamFailureNormalizer.php` requires an explicit `require_once` in `source/source/loader.php`. There is no PSR-4 autoload at runtime. Order: interface and base classes first, concrete clients after.
- `HttpClientInterface` is unchanged. The decorator implements the same `request()` signature as `CurlHttpClient`, so injecting a custom client stays possible.
- `CurlHttpClient::__construct()` becomes optional-argument. Existing call sites `new CurlHttpClient()` keep working with the defaults.
- `CurlHttpExecutor\Base::__construct()` reads the two new option keys with defaults, so every existing subclass (`Get`, `Post`, `Put`, `Patch`, `Delete`) inherits the timeouts without edits.
- `ProxyRequestHandler::__construct()` gains two optional parameters after `$httpClient`. The `build()` factory reads `connect_timeout` and `timeout` and throws `InvalidArgumentException` unless each is a non-negative integer. The constructor always wraps the (injected or default) client in `UpstreamFailureNormalizer`.
- `DefaultProxyRequestHandler::build()` must forward the two keys (with the same validation) to `parent::__construct()`; its own constructor signature grows by two trailing optional parameters.
- `Response::isTimeout()` is a pure read. No setter, no new attribute — it stays a projection of `httpCode`.
- The `errno` / `error` keys are consumed by the normalizer. `ProxyRequestHandler` never sees them, and `new Response($response)` keeps working: `SimpleModel` ignores unknown keys and `Response::DEFAULT_ATTRIBUTES` is unchanged.
- `docs/request-handlers.md` needs the two new options documented in the `default_proxy` table and in the `proxy` section, in a separate documentation commit per the contributing rules.
- No PR opens more than one behavioral change: timeouts and 502/504 mapping ship together as one issue-sized change, documentation separately.
- No new dependency. No environment variable is introduced.

### Testing

PHPUnit, run through `docker compose run --rm tent_tests composer tests:unit`.

#### `source/tests/unit/lib/http/UpstreamFailureNormalizerTest.php`

The normalizer's collaborator is `HttpClientInterface`, so the stub is a client double whose `request()` returns the executor-shaped array. One case per errno plus success:

1. Success — stub returns `['body' => '{"ok":true}', 'httpCode' => 200, 'headers' => ['Content-Type: application/json'], 'errno' => 0, 'error' => '']`; the result keeps body, code and headers, and carries no `errno` / `error` keys.
2. Timeout — stub returns `errno => CURLE_OPERATION_TIMEDOUT`; the result is `httpCode === 504`, `body === ''`, `headers === []`.
3. Connection refused — `errno => CURLE_COULDNT_CONNECT`; result `httpCode === 502`.
4. DNS failure — `errno => CURLE_COULDNT_RESOLVE_HOST`; result `httpCode === 502`.
5. Degenerate — `errno => 0`, `error => ''`, empty body and `httpCode => 0` as produced by a `false` return; result `httpCode === 502`.
6. Leak guard — a stub whose `error` is `Failed to connect to api port 80`; assert the returned `body` is `''` and no returned header contains the error string or the host.
7. Custom client — stub returns `['body' => 'x', 'httpCode' => 200, 'headers' => []]` with no `errno` / `error` keys; result is unchanged.
8. Custom client degenerate — stub returns `httpCode => 0` with no `errno` key; result `httpCode === 502`.
9. Log — the warn line carries errno and error and the upstream URL without its query string (stub URL `http://api:80/health.json?token=secret` never logs `token`).

#### `source/tests/unit/lib/http/CurlHttpClientTest.php`

- `connect_timeout` and `timeout` from the constructor reach the executor's cURL options with the supplied values.
- Both keys default to `5` and `30` when the constructor receives no options.

#### `source/tests/unit/lib/http/CurlHttpExecutor/GetTest.php` and `PostTest.php`

- `CURLOPT_CONNECTTIMEOUT` and `CURLOPT_TIMEOUT` are set on the handle for both verbs, so a future override of `addExtraCurlOptions()` cannot silently drop them.

#### `source/tests/unit/lib/models/ResponseTest.php`

- `isTimeout()` is `true` for `504`, `false` for `502`, `false` for `200`, `false` for `0`.

#### `source/tests/unit/lib/service/ResponseSenderTest.php`

- `httpCode` `0` emits `500` through the injected status emitter.
- `httpCode` `502` and `504` emit themselves.
- Terminal cases use the injectable emitters, so no output or header state is touched.

#### `source/tests/unit/lib/request_handlers/ProxyRequestHandlerTest.php`

Per the design decision, no new mapping test here. The existing coverage of `processsRequest()` stays as is. Add:

- An injected client returning `httpCode => 0` yields a `502` `Response` (proves the injected client is wrapped).
- `build()` with invalid `connect_timeout` / `timeout` (`-1`, `'abc'`) throws `InvalidArgumentException`; `0` is accepted.

The same `build()` validation cases apply to `DefaultProxyRequestHandlerTest.php`.

#### Manual verification

- `default_proxy` pointed at a closed port: `curl -si` shows `502 Bad Gateway` with an empty body.
- A listener that accepts and never responds, with `timeout` set to `2`: `curl -si` shows `504 Gateway Timeout` within the configured limit.
- The same rule with `LOG_LEVEL=warn`: the log carries the errno and the cURL error, and the response body is still empty.

## Benefits

- Clients, load balancers and health probes see a real `5xx` when the upstream is down, instead of a false success.
- `StatusCodeMatcher` rules for `5xx` fire on upstream failures, enabling downstream middlewares (e.g. synthesized health bodies).
- Hung upstreams are cut off by bounded, configurable timeouts.
- Operators get a warn-level log line with the transport cause, without leaking credentials from the query string.
