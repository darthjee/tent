# Executor reports errno and applies timeouts

In `CurlHttpExecutor\Base`:

- Constructor reads two new option keys, `connect_timeout` (default `5`) and `timeout` (default `30`), into `protected int $connectTimeout` / `protected int $timeout`. Update the docblock.
- `initCurlRequest()` sets `CURLOPT_CONNECTTIMEOUT` and `CURLOPT_TIMEOUT` from those properties. All subclasses (`Get`, `Post`, `Put`, `Patch`, `Delete`) inherit this without edits.
- `executeCurlRequest()` captures `curl_errno()` and `curl_error()` before `curl_close()`, treats a `false` return from `curl_exec()` as `''` before the `substr()` calls, and returns `errno` and `error` alongside `body`, `httpCode` and `headers`. Update the `@return` shape in `request()` and `executeCurlRequest()`.

Tests (against `httpbin`):

- `GetTest` / `PostTest`: result carries `errno === 0` and `error === ''` on success.
- `GetTest` / `PostTest`: `http://httpbin/delay/3` with `timeout => 1` returns `errno === CURLE_OPERATION_TIMEDOUT` — proves `CURLOPT_TIMEOUT` is applied on both verbs.
- `GetTest`: an unreachable URL (`http://127.0.0.1:1`) returns a non-zero `errno`, a non-empty `error`, `httpCode === 0` and `body === ''`.

## Files to Change

- `source/source/lib/http/CurlHttpExecutor/Base.php` — timeout options, errno/error capture, `false` guard.
- `source/tests/unit/lib/http/CurlHttpExecutor/GetTest.php` — errno, timeout and unreachable cases.
- `source/tests/unit/lib/http/CurlHttpExecutor/PostTest.php` — errno and timeout cases.
