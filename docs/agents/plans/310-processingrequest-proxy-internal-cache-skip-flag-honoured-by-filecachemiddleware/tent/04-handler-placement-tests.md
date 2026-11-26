# Handler-level placement tests
Add a test-support middleware `SkipCacheMiddleware` under `Tent\Tests\Support\Middlewares` (pattern of `DummyRequestMiddleware`) whose `processRequest()` calls `$request->skipCache()` and returns the request; register it in `source/tests/support/loader.php`.

Add `DefaultProxyRequestHandlerSkipCacheTest.php`, reusing the setup style of `DefaultProxyRequestHandlerCachedTest.php` (temp cache dir, pre-built cache entry, `HttpClientInterface` mock injected via reflection on the parent's `httpClient` property), building the handler with the support middleware via `prependMiddlewares` / `middlewares`:

- **prepend, read**: with a cached entry on disk and the guard in `prependMiddlewares`, the upstream client is called once and the response body is the upstream body, not the cached one;
- **prepend, write**: with no entry and the guard in `prependMiddlewares`, after a 2xx upstream response nothing is stored under the cache dir;
- **no marker header**: the headers passed to the mocked upstream call and the returned response headers contain nothing added by the flag;
- **ordering contract**: with a cached entry and the guard in `middlewares`, the cached body is served and the upstream client is never called (documents that `middlewares` placement does not bypass the read path);
- **write via `middlewares`**: with no entry and the guard in `middlewares`, nothing is stored (write bypass still applies).

## Files to Change
- `source/tests/support/middlewares/SkipCacheMiddleware.php` — new test-support middleware calling `skipCache()`.
- `source/tests/support/loader.php` — `require_once` the new support middleware.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerSkipCacheTest.php` — new handler spec.
