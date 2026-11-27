# Tent Plan: Apache access log: make the format configurable and stop logging query strings and Referer

Main plan: [plan.md](plan.md)

## Shared contracts

- New debug line format, query string never included:
  `[<STATUS>] - upstream response — method: <METHOD>, uri: <PATH>, upstream: <BASE_URL>/<PATH>`
- The upstream HTTP request still uses the full URL with the query string. Only the log line changes.
- `docs/agents/architecture.md` notes that the Apache access log is a separate stream from the PHP `Logger`, configured by the image via `TENT_ACCESS_LOG_FORMAT` (see [plan.md](plan.md) for the presets).

## Implementation Steps

### Step 1 — Log the upstream URL without its query string
In `ProxyRequestHandler::processsRequest()` (`source/source/lib/request_handlers/ProxyRequestHandler.php`), keep building `$url` with `$this->server()->fullUrl($request->requestPath(), $request->query())` for the HTTP call. For the `Logger::debug()` line, use `$this->server()->fullUrl($request->requestPath())` (no query) instead of `$url`. Keep the message shape otherwise identical. A small private helper or a local `$loggedUrl` variable is fine; do not change `Server::fullUrl()`.

### Step 2 — Tests and architecture docs
- In `ProxyRequestHandlerGeneralTest`, add a test following `testLogsDebugForAllUpstreamStatuses` (mocked `LoggerInstance` with `expects($this->once())->method('log')->with(...)`). Use `initVariables(['requestQuery' => 'token=SMOKE'])` and assert the exact message `'[200] - upstream response — method: GET, uri: /api/users, upstream: http://backend:8080/api/users'`, i.e. no `?token=SMOKE`.
- Make sure the existing `testHandleRequestAppendsQueryString` still proves the upstream request receives the query. If it only asserts the response type today, tighten it to assert the URL passed to the mocked `HttpClientInterface::request()` ends in `?page=1&limit=10`, so the split between the request URL and the logged URL is pinned.
- `docs/agents/architecture.md`: in the "Debug log messages" table, describe `<UPSTREAM_URL>` as the upstream base URL plus path, without the query string. Near the Logger section, add a short note: the Apache access log is a separate stream from the PHP `Logger`. It is written by Apache after PHP returns, configured by the image through `TENT_ACCESS_LOG_FORMAT` / `/etc/apache2/tent.d/`, and not by `LOG_LEVEL`.

## Files to Change
- `source/source/lib/request_handlers/ProxyRequestHandler.php` — log line uses the query-less URL.
- `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerGeneralTest.php` — new log test; tightened query-forwarding test.
- `docs/agents/architecture.md` — debug-log table wording; Apache access-log note.

## CI Checks
- `source/`: `docker compose run tent_tests vendor/bin/phpunit` (or the project's usual `make tests` shell), plus the repo's phpcs/phpmd checks (CircleCI test/lint jobs).

## Notes
- Other `Logger` call sites already log `requestPath()` only; no change needed there.
