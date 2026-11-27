# CurlHttpClient forwards timeouts

`CurlHttpClient` gains `public function __construct(array $options = [])` reading `connect_timeout` (default `5`) and `timeout` (default `30`). `request()` adds both values to the executor options array next to `url`, `headers`, `body`, `uploadedFiles` and `postFields`. `new CurlHttpClient()` keeps working unchanged. Update the class docblock (it still says "only GET requests are implemented") and the `@return` shape to include `errno`/`error`.

Tests:

- `new CurlHttpClient(['timeout' => 1])` requesting `GET http://httpbin/delay/3` returns `errno === CURLE_OPERATION_TIMEDOUT`.
- `new CurlHttpClient()` requesting `GET http://httpbin/delay/1` succeeds (`errno === 0`, `httpCode === 200`), proving the default is not lower than the delay.

## Files to Change

- `source/source/lib/http/CurlHttpClient.php` — constructor with options; forward timeouts to executors.
- `source/tests/unit/lib/http/CurlHttpClient/CurlHttpClientGetTest.php` — timeout forwarding and default cases.
