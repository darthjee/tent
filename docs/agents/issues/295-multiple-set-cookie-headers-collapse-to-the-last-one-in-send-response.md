# Issue: Multiple Set-Cookie headers collapse to the last one in send_response

## Description
When an upstream response carries several `Set-Cookie` headers, only the **last** one reaches the client. Headers are kept intact all the way through the pipeline (`CurlUtils::parseResponseHeaders` and `Response::headers()` keep a plain list of lines, duplicates included). They are lost only at the final emit in `send_response` (`source/source/index.php`).

## Problem
`send_response` emits each header line with:

```php
foreach ($response->headers() as $header) {
    header($header);
}
```

PHP's `header()` defaults to `replace = true`, so each `Set-Cookie:` line overwrites the previous one.

Impact: any backend that sets more than one cookie in a single response breaks behind Tent. A real case is Kerghan's auth flow (darthjee/kerghan#324). Login, refresh and logoff each send three cookies (`access_token`, `refresh_token`, `logged_in`). Through Tent only `logged_in` gets set or cleared, so login fails and logout leaves the httpOnly token cookies in place.

## Expected Behavior
- An upstream response with N `Set-Cookie` headers reaches the client with all N.
- Every other header line in `Response::headers()` is still forwarded as-is.

## Solution
1. Move the body of `send_response` into a class (e.g. `Tent\Service\ResponseSender`). It takes an injectable header emitter (a callable that defaults to PHP's `header()`) so the emitted headers can be checked in unit tests. `index.php` becomes a thin call to it.
2. Emit every header without replacing earlier ones that share a name:

```php
foreach ($response->headers() as $header) {
    ($this->emitter)($header, false);
}
```

Use `false` for every header, not only `Set-Cookie`. `Response::headers()` already holds the final header set: `SetHeadersMiddleware` only touches the request, and the response-side filters only remove lines. A proxy should forward repeated upstream headers as it received them.

### Testing
`header()` calls can't be observed under PHPUnit (CLI SAPI, pcov, no xdebug). The unit test therefore injects a spy emitter and checks that a response with 3 `Set-Cookie` lines (plus other headers) leads to one emitter call per line, each with `replace = false`.

### Acceptance
- [ ] An upstream response with N `Set-Cookie` headers reaches the client with all N.
- [ ] `send_response` logic lives in a testable class with an injectable header emitter. `index.php` only delegates to it.
- [ ] A unit test covers several `Set-Cookie` headers being emitted with `replace = false`.

### Out of scope
- Releasing new `darthjee/tent` / `darthjee/tent-test` images. That ships in the usual follow-up "Bump version" PR.

## Benefits
- Backends that set several cookies per response (auth flows with access/refresh tokens) work behind Tent.
- Tent forwards response headers as the upstream sent them.
