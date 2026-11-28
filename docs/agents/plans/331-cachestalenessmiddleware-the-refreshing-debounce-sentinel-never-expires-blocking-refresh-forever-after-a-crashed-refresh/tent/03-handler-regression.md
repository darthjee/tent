# Handler-level regression tests

#311 went unnoticed because nothing drove the middleware through a `RequestHandler`, so cover the takeover end to end with the `StaleCacheScenario` trait (`source/tests/support/handlers/StaleCacheScenario.php`).

In `ProxyRequestHandlerStalenessTest.php`:

- `storeEntry(1000)`, plus a leaked sentinel at `$this->entry()->metaFilePath() . '.refreshing'` with content `(string) (time() - 3600)`. Handle one request through the handler, then assert `$this->scheduled === 1` and that the response body is the cached body.
- Same setup, with `stalenessConfig(['sentinel_ttl_seconds' => 0])` → `$this->scheduled === 0`. The escape hatch keeps the pre-#331 behaviour through the full chain.

No new case in `DefaultProxyRequestHandlerStalenessTest.php`: it uses the same middleware code path. If `StaleCacheScenario` needs a small helper to write the sentinel (e.g. `leakSentinel(int $age)`), add it to the trait.

## Files to Change
- `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerStalenessTest.php` — two cases.
- `source/tests/support/handlers/StaleCacheScenario.php` — optional sentinel helper.
