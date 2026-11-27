# Proxy handlers: wrap client and accept timeout params

`ProxyRequestHandler`:

- Constructor becomes `__construct(string $host, ?HttpClientInterface $httpClient = null, int $connectTimeout = 5, int $timeout = 30)` and **always** wraps: `$this->httpClient = new UpstreamFailureNormalizer($httpClient ?? new CurlHttpClient(['connect_timeout' => $connectTimeout, 'timeout' => $timeout]));`.
- Add a shared `protected static function timeoutParam(array $params, string $key, int $default): int` that returns the default when the key is absent and throws `InvalidArgumentException` unless the value `is_int` and `>= 0`.
- `build()` reads `connect_timeout` and `timeout` through that helper and passes them to the constructor.
- Docblocks updated (constructor, `build()`, class example optional).

`DefaultProxyRequestHandler`:

- Constructor gains two trailing optional params `int $connectTimeout = 5, int $timeout = 30`, forwarded to `parent::__construct($host, $httpClient, $connectTimeout, $timeout)`.
- `build()` reads both keys through `self::timeoutParam()` and passes them; docblock lists them.

Tests:

- `ProxyRequestHandlerBuildTest`: `build()` with `connect_timeout`/`timeout` integers (including `0`) succeeds; `-1`, `'abc'`, `2.5` throw `InvalidArgumentException` for each key.
- `ProxyRequestHandlerGeneralTest`: an injected stub client returning `['body' => '', 'httpCode' => 0, 'headers' => []]` yields a `Response` with `httpCode() === 502` (proves the injected client is wrapped); existing coverage stays.
- `DefaultProxyRequestHandlerBuildTest`: same valid/invalid timeout cases.

## Files to Change

- `source/source/lib/request_handlers/ProxyRequestHandler.php` — constructor, wrapping, validation helper, `build()`.
- `source/source/lib/request_handlers/DefaultProxyRequestHandler.php` — constructor and `build()` forwarding.
- `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerBuildTest.php` — timeout validation cases.
- `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerGeneralTest.php` — injected-client wrapping case.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerBuildTest.php` — timeout validation cases.
