# UpstreamFailureNormalizer

New class `Tent\Http\UpstreamFailureNormalizer implements HttpClientInterface`, constructed with the decorated `HttpClientInterface`. `request()` has the same signature as `CurlHttpClient::request()` and:

1. Calls the decorated client.
2. Reads `errno` (`?? 0`), `error` (`?? ''`) and `httpCode` (`?? 0`).
3. `errno === CURLE_OPERATION_TIMEDOUT` → returns `['body' => '', 'httpCode' => 504, 'headers' => []]`.
4. Any other non-zero `errno` → `['body' => '', 'httpCode' => 502, 'headers' => []]`.
5. `errno === 0` and `httpCode === 0` → `502`, same shape.
6. Otherwise returns the decorated result with `errno` and `error` removed.
7. On every synthesized case, `Logger::warn('[<code>] - upstream <unreachable|timeout> — method: <METHOD>, url: <url without query>, errno: <n>, error: <error>')`. Strip the query string with `strtok($url, '?')` or `parse_url` reconstruction; neither the error nor the host ever goes into the returned array.

Register it in `source/source/loader.php` with `require_once`, after `HttpClientInterface.php` and `CurlHttpClient.php` (it only depends on the interface).

Tests in a new `source/tests/unit/lib/http/UpstreamFailureNormalizerTest.php`, using a stub `HttpClientInterface` returning fixed arrays. Install a capturing `LoggerInstance` via `Logger::setInstance()` (as the existing tests do) for the log case:

1. Success with `errno 0` → body/code/headers kept; no `errno` / `error` keys.
2. `CURLE_OPERATION_TIMEDOUT` → `504`, `body === ''`, `headers === []`.
3. `CURLE_COULDNT_CONNECT` → `502`.
4. `CURLE_COULDNT_RESOLVE_HOST` → `502`.
5. Degenerate `errno 0`, `httpCode 0` → `502`.
6. Leak guard: `error` = `Failed to connect to api port 80`, upstream headers present → `body === ''`, `headers === []`.
7. Custom client result without `errno`/`error` keys and `httpCode 200` → unchanged.
8. Custom client result without `errno` and `httpCode 0` → `502`.
9. Log: URL `http://api:80/health.json?token=secret` → one warn line containing `errno: 7`, the error text and `http://api:80/health.json`, and not containing `token`.
10. A real upstream `502`/`504` with `errno 0` → passed through unchanged (body and headers kept), no warn line.

## Files to Change

- `source/source/lib/http/UpstreamFailureNormalizer.php` — new decorator.
- `source/source/loader.php` — `require_once` for the new class.
- `source/tests/unit/lib/http/UpstreamFailureNormalizerTest.php` — new test file.
