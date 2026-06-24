# Implement Cache Staleness Middleware

## Context

Tent's existing caching middlewares (e.g. `FileCacheMiddleware`) serve cached responses but do not track how old a cached entry is relative to when the backend was last actually contacted. This means a stale cache entry is served indefinitely until something else (TTL expiry, manual purge, cleanup middleware) removes it — there is no graceful "serve stale, refresh in background" behavior. A staleness-aware middleware would let cached responses keep being served instantly while transparently triggering a background refresh once they pass a configurable age threshold, improving perceived latency without sacrificing freshness over time.

## What needs to be done

- **Tracking**: add a `CacheStalenessMiddleware` that records the timestamp of the last backend contact for each cached resource, updated whenever a fresh response is fetched from the backend and stored in cache.
- **Staleness detection**: before serving a cached response, check its age against a configurable threshold (e.g. `maxAgeSeconds`) to determine whether it is stale.
- **Response behavior**:
  - Fresh cache: serve the cached response as normal.
  - Stale cache: serve the cached response immediately, but trigger a non-blocking background request to refresh the cache for future requests.
  - The background refresh must not delay or block the current response.
- **Configuration**: support a configurable staleness threshold (max age in seconds) in the middleware configuration, similar to other Tent middlewares, with the option for per-rule configuration. Example:
  ```php
  Configuration::buildRule([
    'handler' => [ ... ],
    'matchers' => [ ... ],
    'middlewares' => [
      [
        'class' => 'Tent\\Middlewares\\CacheStalenessMiddleware',
        'location' => './cache',
        'maxAgeSeconds' => 300, // Cache considered stale after 5 minutes
      ]
    ]
  ]);
  ```
- **Integration**: the middleware should be configurable in Tent rules like other middlewares, and should work alongside `FileCacheMiddleware`, respecting the same cache location settings.
- **Implementation notes**:
  - Store and retrieve last-contact timestamps efficiently (e.g. alongside cache files or in a metadata store).
  - Log staleness checks and background refresh actions for debugging/auditing.
  - Avoid triggering excessive background refreshes (debounce repeated stale hits if needed).
  - Document the middleware and provide usage examples.

## Acceptance criteria

- [ ] Middleware tracks the last backend contact timestamp per cached resource.
- [ ] Stale cache responses are served immediately to the client, while a background refresh of the cache is triggered.
- [ ] The staleness threshold (max age) is configurable via middleware configuration.
- [ ] The middleware integrates with `FileCacheMiddleware` and respects its cache location settings.
- [ ] Background refreshes do not block or delay the response being served.
- [ ] Documentation and a usage example are added.
