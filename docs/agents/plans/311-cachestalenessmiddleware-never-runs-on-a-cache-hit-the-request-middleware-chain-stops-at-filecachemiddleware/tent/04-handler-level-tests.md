# Handler-level tests

Add tests that drive `CacheStalenessMiddleware` through a real handler (inject the scheduler via reflection as existing tests do; use a temp cache `location` with a pre-written entry whose meta `timestamp` is controlled):

- `ProxyRequestHandler` with `FileCacheMiddleware` + `CacheStalenessMiddleware` in `middlewares`: stale hit → scheduler invoked; fresh hit → not invoked. (Fails on `main` today.)
- `DefaultProxyRequestHandler` (built-in `FileCacheMiddleware`) with `CacheStalenessMiddleware` in `middlewares`: stale hit → scheduler invoked.
- `CacheStalenessMiddleware` in `prependMiddlewares`: stale hit → scheduler invoked.
- Short-circuit regression: a spy request middleware placed after `FileCacheMiddleware` has its `processRequest()` not called on a hit.
- Provenance: a custom middleware that sets a response, with an old entry on disk → scheduler not invoked.
- Eviction on read (`FileCacheMiddleware` `evictOn` matching the stored status) → scheduler not invoked.
- Custom `request_hasher` on `FileCacheMiddleware`: stale hit refreshes the same entry that was served.
- Header filter: the refresh write applies `FileCacheMiddleware`'s filter (e.g. a header excluded by `FileCacheMiddleware` but not by the staleness middleware's deprecated options is not stored).

## Files to Change
- `source/tests/unit/lib/request_handlers/ProxyRequestHandler/` — new staleness handler test file.
- `source/tests/unit/lib/request_handlers/DefaultProxyRequestHandler/` — new staleness handler test file.
