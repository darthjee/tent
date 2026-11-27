# Plan: Proxy answers an empty 200 when the upstream is unreachable; add 502/504 and curl timeouts

Issue: [315-proxy-answers-an-empty-200-when-the-upstream-is-unreachable-add-502-504-and-curl-timeouts.md](../../issues/315-proxy-answers-an-empty-200-when-the-upstream-is-unreachable-add-502-504-and-curl-timeouts.md)

## Overview

Transport-level cURL failures stop surfacing as an empty `200`. The executor reports `errno`/`error`, a new `Tent\Http\UpstreamFailureNormalizer` decorator maps them to `502` (or `504` on `CURLE_OPERATION_TIMEDOUT`), the proxy handlers gain validated `connect_timeout` (5) / `timeout` (30) options, `ResponseSender` clamps invalid statuses to `500`, and `Response::isTimeout()` is added. The user-facing handler docs are updated in a separate documentation commit.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

Handler params, accepted by both `proxy` (`ProxyRequestHandler::build()`) and `default_proxy` (`DefaultProxyRequestHandler::build()`):

| Key | Type | Default | Validation | Effect |
|---|---|---|---|---|
| `connect_timeout` | `int` (seconds) | `5` | non-negative integer, else `InvalidArgumentException` | `CURLOPT_CONNECTTIMEOUT`; `0` disables |
| `timeout` | `int` (seconds) | `30` | non-negative integer, else `InvalidArgumentException` | `CURLOPT_TIMEOUT`; `0` disables |

Behaviour to document (produced by `tent`, described by `product-dev-extending`):

- Transport failure (any non-zero cURL errno except timeout, or `httpCode 0`) → `502`, empty body, no headers.
- cURL timeout (`CURLE_OPERATION_TIMEDOUT`, connect or total) → `504`, empty body, no headers.
- The synthesized status crosses the response middleware chain, so `StatusCodeMatcher` with `5xx` matches; default cache rules (`2xx`) never cache it.
- A warn-level log line carries method, upstream URL without the query string, errno and cURL error.
- An injected `httpClient` is always wrapped by the normalizer; the timeout options only configure the default `CurlHttpClient`.
- `Response::isTimeout()` is `true` iff `httpCode() === 504`.

## Notes

- `docs/agents/architecture.md` (line ~117, "HTTP Client") should mention `UpstreamFailureNormalizer` — architect scope, to be done alongside the documentation commit.
- One behavioral change per PR: code + tests in one commit series, documentation in a separate commit (contributing rules).
