# Response::isTimeout() and ResponseSender guard

- `Response::isTimeout(): bool` returns `$this->httpCode() === 504`. Pure read, no new attribute, `DEFAULT_ATTRIBUTES` unchanged.
- `ResponseSender::send()` computes `$code = $response->httpCode()` and replaces it with `500` when `< 100` or `> 599` before calling the status emitter.

Tests:

- `ResponseTest`: `isTimeout()` is `true` for `504`, `false` for `502`, `200` and `0`.
- `ResponseSenderTest` (injected emitters): `httpCode 0` emits `500`; `600` emits `500`; `502` and `504` emit themselves.

## Files to Change

- `source/source/lib/models/Response.php` — `isTimeout()`.
- `source/source/lib/service/ResponseSender.php` — status clamp.
- `source/tests/unit/lib/models/ResponseTest.php` — `isTimeout()` cases.
- `source/tests/unit/lib/service/ResponseSenderTest.php` — clamp cases.
