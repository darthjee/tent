# Tent Plan: Multiple Set-Cookie headers collapse to the last one in send_response

Main plan: [plan.md](plan.md)

## Overview
`send_response` in `source/source/index.php` calls `header($header)` with PHP's default `replace = true`, so only the last of several same-named headers (e.g. `Set-Cookie`) reaches the client. Extract the logic into a `ResponseSender` service with an injectable header emitter, emit with `replace = false`, and unit-test it with a spy emitter.

## Context
- `CurlUtils::parseResponseHeaders` and `Response::headers()` keep a plain list of header lines, duplicates included. The loss happens only in the final emit.
- `Response::headers()` already holds the final header set: `SetHeadersMiddleware` only touches the request, and the response-side header filters only remove lines. Using `replace = false` for every header is therefore safe.
- `header()` calls can't be observed under PHPUnit (CLI SAPI, pcov, no xdebug), hence the injectable emitter.

## Implementation Steps

### Step 1 — Add `Tent\Service\ResponseSender`
Create `source/source/lib/service/ResponseSender.php` (namespace `Tent\Service`), following the docblock style of the other services:
- Constructor takes an optional `?callable $headerEmitter = null`. It defaults to a closure that calls PHP's `header($line, $replace)`.
- `send(Response $response): void` runs `http_response_code($response->httpCode())`, then for each line in `$response->headers()` calls `($this->headerEmitter)($header, false)`, then `echo $response->body()`.
- Register it in `source/source/loader.php` next to the other `lib/service/` requires.
- Replace `send_response` in `source/source/index.php` with a thin delegation: `(new ResponseSender())->send($response);` (keep or remove the `send_response` wrapper. Keeping it as a one-line wrapper is fine). Add the `use Tent\Service\ResponseSender;` import.

### Step 2 — Unit test
Create `source/tests/unit/lib/service/ResponseSenderTest.php` (namespace `Tent\Tests\Service`, requiring `../../../support/loader.php` like `ResponseCacherTest`):
- Build a `Response` with several headers, including 3 `Set-Cookie` lines (`access_token=...`, `refresh_token=...`, `logged_in=...`) and e.g. `Content-Type: application/json`.
- Inject a spy emitter that records `[$line, $replace]` pairs. Wrap `send()` in `ob_start()` / `ob_get_clean()` to capture the body.
- Assert that every header line was emitted once, in order, each with `replace === false`, that all 3 `Set-Cookie` lines are present, and that the echoed body matches `Response::body()`.
- `http_response_code()` is harmless under CLI and needs no assertion.

## Files to Change
- `source/source/lib/service/ResponseSender.php` — new service that emits status, headers (`replace = false`) and body.
- `source/source/loader.php` — require the new service.
- `source/source/index.php` — delegate to `ResponseSender` instead of calling `header($header)` directly.
- `source/tests/unit/lib/service/ResponseSenderTest.php` — new unit test covering multiple `Set-Cookie` headers.

## CI Checks
- `source`: `composer coverage` (CI job: tent tests)
- `source`: `composer lint` (CI job: `checks`)

## Notes
- Releasing new `darthjee/tent` / `darthjee/tent-test` images is out of scope. It ships in the usual follow-up "Bump version" PR.
- Check that the default emitter closure passes `$replace` through to `header()`. Don't hardcode `true` anywhere.
