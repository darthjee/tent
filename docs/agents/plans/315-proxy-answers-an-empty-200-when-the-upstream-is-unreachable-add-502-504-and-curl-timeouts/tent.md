# Tent Plan: Proxy answers an empty 200 when the upstream is unreachable; add 502/504 and curl timeouts

Main plan: [plan.md](plan.md)

## Shared contracts

- `connect_timeout` (int, default `5`) and `timeout` (int, default `30`) on `ProxyRequestHandler::build()` and `DefaultProxyRequestHandler::build()`; non-negative integers only (`0` allowed), otherwise `InvalidArgumentException`.
- Executor result array: `['body' => string, 'httpCode' => int, 'headers' => string[], 'errno' => int, 'error' => string]`. `errno`/`error` are stripped by the normalizer before the handler sees the result.
- Normalizer mapping: `errno === CURLE_OPERATION_TIMEDOUT` → `504`; any other non-zero errno → `502`; `errno === 0` (or missing) with `httpCode === 0` → `502`; otherwise pass-through. Synthesized result: `['body' => '', 'httpCode' => <502|504>, 'headers' => []]`.
- Log line (`Logger::warn()`): `[<code>] - upstream unreachable — method: <METHOD>, url: <url without query>, errno: <n>, error: <curl_error>` (use `upstream timeout` as reason for 504).
- `Response::isTimeout(): bool` — `httpCode === 504`.
- `ResponseSender::send()` emits `500` for any `httpCode` outside `100..599`.

## Steps

- [01 — Executor reports errno and applies timeouts](tent/01-executor-errno-and-timeouts.md)
- [02 — CurlHttpClient forwards timeouts](tent/02-curl-http-client-timeouts.md)
- [03 — UpstreamFailureNormalizer](tent/03-upstream-failure-normalizer.md)
- [04 — Proxy handlers: wrap client and accept timeout params](tent/04-proxy-handlers.md)
- [05 — Response::isTimeout() and ResponseSender guard](tent/05-response-and-sender.md)

## CI Checks

- `source/`: `docker compose run --rm tent_tests composer tests:unit` (CI job: `unit_test`, runs `composer coverage` with an `httpbin` service)
- `source/`: `docker compose run --rm tent_tests composer lint` (CI job: `checks`)

## Notes

- Executor/client tests hit the real `httpbin` service; there is no way to read options back from a `CurlHttpHandle`. Prove timeouts behaviourally: `http://httpbin/delay/3` with `timeout => 1` must yield `errno === CURLE_OPERATION_TIMEDOUT` (28); an unreachable target such as `http://127.0.0.1:1` must yield a non-zero errno and `httpCode 0`. Keep delays small to protect CI time.
- `curl_errno()` / `curl_error()` must be read **before** `curl_close()`.
- When `curl_exec()` returns `false`, guard the `substr()` calls (treat the response as `''`) so no deprecation/notice is raised.
- `new Response($response)` keeps working with extra keys (SimpleModel ignores unknown keys), but the normalizer strips them anyway.
- Reuse a single validation helper for the two timeout keys so `ProxyRequestHandler::build()` and `DefaultProxyRequestHandler::build()` share it (e.g. a `protected static` method on `ProxyRequestHandler`).
