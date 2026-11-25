# CacheStalenessMiddleware: optionally evict the entry when a background refresh fails

## Problem

`CacheStalenessMiddleware` serves a stale cache hit immediately and schedules a `BackgroundRefresher`
to re-contact the upstream. Today, when that refresh gets a non-successful response,
`BackgroundRefresher::keepStaleCache()` leaves the stale entry in place — it only logs a warning and
returns. The stale entry keeps being served on every later hit, and no request ever reaches the
backend again.

For a shared cache this is the correct default: a transient `500` must not destroy a good entry.

For a **per-caller** cache it is a security problem. When the cache key is derived from a credential —
a `request_hasher` keyed on an access-token cookie or an `Authorization` header — a caller whose
credential is revoked or expired receives `401`/`403`/`404` from the upstream on the refresh, but the
stale entry survives and is served again on the next hit. Because the response never leaves the cache,
the backend's authorization check is never reached. The cached body stays readable for as long as
requests keep arriving for that key.

The same gap exists on the write side. `FileCacheMiddleware::processResponse()` stores a response when
it passes the configured matchers, and `processRequest()` serves whatever it finds on disk without ever
questioning the status code that was stored. There is no way to declare that a given status family must
never be represented in the cache, nor to purge an entry that already carries one.

Surfaced by darthjee/citadel#33, which caches per caller keyed on the access-token cookie. Citadel works
around it by skipping the cache entirely for expired tokens, but that still leaves a window for revoked,
unexpired tokens.

## Current behaviour (reference)

| Location | Behaviour today |
| --- | --- |
| [`CacheStalenessMiddleware::triggerRefresh()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Debounces concurrent refreshes with a sentinel file at `<metaFilePath>.refreshing`. No notion of what to do with the result. |
| [`CacheStalenessMiddleware::scheduleRefresh()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/CacheStalenessMiddleware.php) | Builds a `BackgroundRefresher` with five fixed arguments: request, cache, host, httpClient, headerFilter. Nothing about failure policy is passed through. |
| [`BackgroundRefresher::refresh()`](https://github.com/darthjee/tent/blob/main/source/source/lib/service/BackgroundRefresher.php) | Binary branch: `$response->isSuccessful()` calls `replaceCache()`, anything else calls `keepStaleCache()`. |
| [`BackgroundRefresher::keepStaleCache()`](https://github.com/darthjee/tent/blob/main/source/source/lib/service/BackgroundRefresher.php) | Only logs `Logger::warn('[<code>] - background cache refresh got a non-successful response, keeping stale cache — uri: <uri>')`. No filesystem mutation. |
| [`BackgroundRefresher::replaceCache()`](https://github.com/darthjee/tent/blob/main/source/source/lib/service/BackgroundRefresher.php) | Calls `$this->cache->remove()` then `ResponseCacher::process()`, because `ResponseCacher` only stores into a cache that does not already `exist()`. |
| [`BackgroundRefresher::attemptRefresh()`](https://github.com/darthjee/tent/blob/main/source/source/lib/service/BackgroundRefresher.php) | Wraps `refresh()` in `try/catch (\Throwable)` and forwards to `handleFailure()`, which only logs a warning. |
| [`FileCache::remove()`](https://github.com/darthjee/tent/blob/main/source/source/lib/content/FileCache.php) | Unlinks `$bodyFilePath` and `$metaFilePath` when present and resets the memoized `$content`. Already the exact primitive an eviction needs. |
| [`FileCache::httpCode()`](https://github.com/darthjee/tent/blob/main/source/source/lib/content/FileCache.php) | Reads `httpCode` from the meta JSON, defaulting to `200` when absent. |
| [`FileCacheMiddleware::processRequest()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/FileCacheMiddleware.php) | On `$cache->exists()`, builds the response via `ResponseContentReader` and calls `$request->setResponse()`. The stored status code is never inspected for policy. |
| [`FileCacheMiddleware::processResponse()`](https://github.com/darthjee/tent/blob/main/source/source/lib/middlewares/FileCacheMiddleware.php) | Stores only when every configured matcher passes (`isCacheable()`), after `shouldSkipCacheWrite()`. Status filtering exists solely as an opt-in `StatusCodeMatcher`. |
| [`HttpCodeMatcher::match()`](https://github.com/darthjee/tent/blob/main/source/source/lib/utils/HttpCodeMatcher.php) | Matches an integer code against an exact value, or against a wildcard pattern like `4xx` / `30x` by translating `x` to `\d`. The reusable primitive for pattern matching. |

## Proposal

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://backend:8080'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/profile', 'type' => 'begins_with']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'evictOn' => ['4xx'],
            'request_hasher' => [
                'class' => 'Tent\\Cache\\HeaderAwareRequestHasher',
                'headerName' => 'Cookie'
            ]
        ],
        [
            'class' => 'Tent\\Middlewares\\CacheStalenessMiddleware',
            'location' => './cache',
            'host' => 'http://backend:8080',
            'maxAgeSeconds' => 10,
            'evictOn' => [401, 403, 404]
        ]
    ]
]);
```

A shared policy object carries the rule for both middlewares.

```php
namespace Tent\Content;

use Tent\Utils\HttpCodeMatcher;

/**
 * Decides whether a given HTTP status code must purge a cache entry instead of
 * being kept (or stored). Accepts exact integers and wildcard patterns such as '4xx',
 * both resolved through HttpCodeMatcher.
 */
class EvictionPolicy
{
    /** @var array<int, string|int> */
    private array $codes;

    public function __construct(array $codes = [])
    {
        $this->codes = $codes;
    }

    public static function build(?array $codes, array $default = []): self
    {
        return new self($codes ?? $default);
    }

    public function evicts(int $httpCode): bool
    {
        foreach ($this->codes as $code) {
            if ((new HttpCodeMatcher($code))->match($httpCode)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->codes === [];
    }
}
```

Proposed constructor and `build()` for `CacheStalenessMiddleware` — the new argument is appended last, keeping the existing signature backward compatible.

```php
public function __construct(
    FolderLocation $location,
    int $maxAgeSeconds,
    string $host,
    ?HttpClientInterface $httpClient = null,
    ?callable $scheduler = null,
    ?array $excludedHeaders = null,
    ?array $additionalExcludedHeaders = null,
    ?array $allowedHeaders = null,
    string $mode = 'deny',
    ?array $evictOn = null
) {
    // ... existing assignments unchanged ...
    $this->evictionPolicy = EvictionPolicy::build($evictOn);
}

public static function build(array $attributes): CacheStalenessMiddleware
{
    $evictOn = $attributes['evictOn'] ?? $attributes['evict_on'] ?? null;

    return new self(
        // ... existing arguments unchanged ...
        $attributes['mode'] ?? 'deny',
        $evictOn
    );
}
```

Proposed constructor and `build()` for `FileCacheMiddleware` — same append-last rule, and the default is also disabled (`[]`), so existing deployments keep caching client-error responses unless they opt in.

```php
public function __construct(
    FolderLocation $location,
    array $matchers = [],
    ?string $skipCacheHeader = null,
    ?RequestHasher $requestHasher = null,
    ?string $requireCacheHeader = null,
    ?array $excludedHeaders = null,
    ?array $additionalExcludedHeaders = null,
    ?array $allowedHeaders = null,
    string $mode = 'deny',
    ?array $evictOn = null
) {
    // ... existing assignments unchanged ...
    $this->evictionPolicy = EvictionPolicy::build($evictOn);
}
```

Proposed new branch in `BackgroundRefresher::refresh()`, and the pass-through in `CacheStalenessMiddleware::scheduleRefresh()`.

```php
private function refresh(string $uri): void
{
    $response = $this->fetch();

    if ($response->isSuccessful()) {
        $this->replaceCache($response);
    } elseif ($this->evictionPolicy->evicts($response->httpCode())) {
        $this->evictCache($uri, $response);
    } else {
        $this->keepStaleCache($uri, $response);
    }

    Logger::debug(
        '[' . $response->httpCode() . '] - background cache refresh completed — uri: ' . $uri
    );
}

private function evictCache(string $uri, Response $response): void
{
    $this->cache->remove();

    Logger::warn(
        '[' . $response->httpCode() . '] - background cache refresh evicted stale entry — uri: ' . $uri
    );
}
```

```php
private function scheduleRefresh(ProcessingRequest $request, FileCache $cache, string $sentinel): void
{
    $refresher = new BackgroundRefresher(
        $request,
        $cache,
        $this->host,
        $this->httpClient,
        $this->headerFilter,
        $this->evictionPolicy
    );

    ($this->scheduler)(function () use ($refresher, $sentinel) {
        try {
            $refresher->run();
        } finally {
            self::removeSentinel($sentinel);
        }
    });
}
```

Current `keepStaleCache()` kept as the `before` reference — it stays untouched and remains the fallback branch.

```php
private function keepStaleCache(string $uri, Response $response): void
{
    Logger::warn(
        '[' . $response->httpCode() . '] - background cache refresh got a non-successful response, ' .
        'keeping stale cache — uri: ' . $uri
    );
}
```

### Semantics

1. `evictOn` is a list whose entries are either integers (`401`) or wildcard patterns (`4xx`). Each entry is matched through `HttpCodeMatcher`, so both forms share one matching implementation.
2. Both spellings are accepted in configuration: `evictOn` and `evict_on`, mirroring the existing `maxAgeSeconds` / `max_age_seconds` pair. Camel case wins when both are present.
3. An absent key resolves to the middleware default. An explicitly empty list (`[]`) disables eviction and restores the pre-change behaviour exactly.
4. On `CacheStalenessMiddleware`, the default is `[]`. Any non-2xx keeps the stale entry, as the issue requires.
5. On `FileCacheMiddleware`, the default is also `[]`. Deployments may legitimately cache `404`s today, so changing the default would be a breaking change. Per-caller caches opt in with `evictOn: ['4xx']`.
6. A status code matching the policy purges the entry: `FileCache::remove()` deletes body and meta. The directory itself is never touched.
7. Network failures and thrown exceptions never evict. `attemptRefresh()` keeps catching everything and routing to `handleFailure()`.
8. Eviction is evaluated only on the response status. Response bodies, headers and the `HeaderFilter` play no role in the decision.
9. The stale response already handed to the current client is never modified, replaced or recalled. Eviction affects only future requests.

### Lifecycle behaviour

The request that detects staleness keeps its existing shape: `processRequest()` sees a response already set by `FileCacheMiddleware`, computes `age = time() - timestamp`, and delegates to `triggerRefresh()` when the entry is older than `maxAgeSeconds`.

What changes is what happens after the sentinel is claimed. The `BackgroundRefresher` now has three outcomes instead of two:

1. Successful upstream response — `replaceCache()`, unchanged.
2. Non-successful response whose code matches the policy — `evictCache()`, the new branch.
3. Non-successful response whose code does not match, or a thrown exception — `keepStaleCache()` or `handleFailure()`, unchanged.

Because the sentinel is removed in a `finally` block around `run()`, it is cleaned up on the eviction path as well. No orphaned `.refreshing` file is introduced by this change.

On `FileCacheMiddleware`, the policy is applied at the two points that already touch storage:

- Read path: `processRequest()` builds the `FileCache` and checks `exists()`; when the stored `httpCode()` matches the policy, it calls `remove()` and returns the request unmodified, so the handler runs and the upstream is contacted normally.
- Write path: `processResponse()` consults the policy before `isCacheable()`; a matching status removes any entry for that key and skips the write.

### State detection

Staleness detection is unchanged and remains timestamp-based: `FileCache::timestamp()` reads the `timestamp` field written by `buildMeta()`, and a `null` timestamp short-circuits the middleware. Eviction detection is status-based and independent: it reads `Response::httpCode()` on the refresh result, or `FileCache::httpCode()` on the stored entry. The two mechanisms never interact — an entry can be fresh and still eviction-eligible.

### Configuration validation

An invalid `evictOn` must fail loudly at build time, following the precedent already set by `mode` throwing `\InvalidArgumentException`:

1. A non-array value throws.
2. An entry that is neither an integer nor a wildcard string matching the `HttpCodeMatcher` pattern shape throws.
3. An empty array is valid and means disabled.

## Expected

1. A `CacheStalenessMiddleware` configured without `evictOn` behaves exactly as before the change: every non-2xx keeps the stale entry and logs the existing warning.
2. A `CacheStalenessMiddleware` configured with `evictOn: [401, 403, 404]` removes body and meta when the refresh returns one of those codes, and the next request for that key reaches the upstream.
3. A `CacheStalenessMiddleware` configured with `evictOn: ['4xx']` removes the entry for any status from 400 to 499 inclusive.
4. A thrown exception during the refresh never removes the entry, regardless of policy.
5. A `2xx` refresh still repopulates the entry through `replaceCache()`, and the configured `HeaderFilter` still applies to the stored headers.
6. The sentinel file `<metaFilePath>.refreshing` is absent after every refresh outcome, including eviction.
7. A `FileCacheMiddleware` configured with `evictOn: ['4xx']` stops serving entries stored with a `4xx` status, and refuses to store new `4xx` responses.
8. A `FileCacheMiddleware` configured without `evictOn`, or with `evictOn: []`, behaves exactly as before the change for any stored status.
9. The stale response returned to the client on the detecting request is byte-identical with and without eviction.
10. Neither middleware throws at runtime for any status code; invalid configuration throws only at build time.

## Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Where the rule lives | A dedicated `Tent\Content\EvictionPolicy` in `source/source/lib/content/` | Both middlewares need the same matching semantics. Duplicating the loop in two classes would let them drift apart, the same risk the header-filter options already carry. |
| Matching engine | Delegate to the existing `Tent\Utils\HttpCodeMatcher` | Wildcard support (`4xx`, `30x`) already exists and is covered by `StatusCodeMatcher`. Writing a second matcher would duplicate logic. |
| Accepted value types | Integers and wildcard strings | The issue example uses integers; the repository convention everywhere else keeps the wildcard form available. Supporting both costs one delegation. |
| Default on `CacheStalenessMiddleware` | `[]` (disabled) | The issue states that the default must stay as today. Any other default silently changes the failure policy of every existing deployment. |
| Default on `FileCacheMiddleware` | `[]` (disabled) | Same as `CacheStalenessMiddleware`: caching `404`s is legitimate for shared caches, so a `4xx` default would silently break existing deployments. Per-caller setups opt in explicitly. |
| Configuration key spelling | `evictOn` and `evict_on`, camel taking precedence | Mirrors `maxAgeSeconds` / `max_age_seconds`, already implemented in the same `build()`. |
| Position in the constructor | Appended as the last parameter | Existing call sites keep working. `CacheStalenessMiddlewareProcessRequestTest` constructs the class positionally and must keep compiling. |
| Exception behaviour | Never evicts | A network failure says nothing about the credential's validity. Evicting on a timeout would turn an upstream hiccup into a cache stampede. |
| Non-evaluated response attributes | Body, headers and `HeaderFilter` are ignored | Status alone determines authorization failure. Keeping the decision narrow makes it auditable. |
| Entry removal primitive | Reuse `FileCache::remove()` | It already deletes body and meta and is what `replaceCache()` uses. Introducing a second removal path would risk leaving meta behind. |

### Edge cases (pinned)

1. Empty `evictOn` list: no eviction ever occurs. Equivalent to omitting the option on `CacheStalenessMiddleware`.
2. `evictOn: ['4xx']` and a `418` refresh result: entry removed. `HttpCodeMatcher` translates the pattern to `/^4\d\d$/`.
3. `evictOn: [404]` and a `405` refresh result: entry kept, existing warning logged.
4. Exception thrown by `CurlHttpClient` during `fetch()`: caught in `attemptRefresh()`, `handleFailure()` logs, entry kept.
5. Successful `2xx` whose write later throws inside `ResponseCacher`: falls into `attemptRefresh()`'s catch, treated as a failure, never as an eviction.
6. Two concurrent stale requests for the same key: the sentinel allows one refresh. Only that one can evict. The second returns early with the existing `skipping` debug message.
7. Eviction while the entry is already gone on disk: `FileCache::remove()` guards with `FileUtils::exists()` on both paths, so the call is idempotent.
8. Entry whose meta lacks `httpCode`: `FileCache::httpCode()` returns `200`, so it is never evicted by a `4xx` policy.
9. Entry whose meta lacks `timestamp`: `CacheStalenessMiddleware` short-circuits before the policy is ever consulted.
10. A `HEAD` or `POST` request replayed by the refresher: the policy applies to the resulting status regardless of method. The refresher already replays method, body, uploaded files and post fields.
11. Both middlewares configured for the same `location` with different `evictOn` lists: each acts on its own code path. Documentation must state that they should match, exactly as it already does for the header-filter options.
12. Nested cache directories under the same `location`: eviction is per hash key, so sibling entries are untouched.
13. A policy entry expressed as a string integer, e.g. `'404'`: `HttpCodeMatcher::match()` compares string-to-string and matches. Accepted, not rejected at build time.
14. `evictOn` present as both `evictOn` and `evict_on` with different lists: camel case wins. No exception, matching the `maxAgeSeconds` precedent.

## Implementation notes

`EvictionPolicy` is a new file at `source/source/lib/content/EvictionPolicy.php`. Because Tent has no Composer PSR-4 autoloading at runtime, it must be added to `source/source/loader.php` with an explicit `require_once __DIR__ . '/lib/content/EvictionPolicy.php';`. Ordering matters by repository convention: dependency-first, so the require should sit after `lib/utils/HttpCodeMatcher.php` rather than inside the early `lib/content/` block that precedes it. Forgetting this require is the classic route to a class-not-found error here.

`CacheStalenessMiddleware` gains a private `EvictionPolicy $evictionPolicy` property. Its docblock must be updated in the same commit: the header-filtering paragraph currently ends without mentioning any other pass-through option, and the "Non-blocking caveat" paragraph should stay untouched.

`BackgroundRefresher` gains the same private property plus one constructor parameter and one private method. Its docblock currently describes `run()` as the entry point and lists the constructor arguments in prose; both need the new argument. `replaceCache()` and `keepStaleCache()` stay unchanged.

`FileCacheMiddleware` gains the property and the two enforcement points. `isCacheable()` keeps its meaning — policy is a separate gate, checked before it, so a `4xx` is rejected by policy even when a permissive `StatusCodeMatcher` would have allowed it.

The write-path removal in `FileCacheMiddleware::processResponse()` must happen before `isCacheable()` returns and before `ResponseCacher` runs. `ResponseCacher::process()` already refuses to store into an existing cache, so a leftover entry would silently block the fresh one; removing first avoids that.

Granularity: one PR for `EvictionPolicy` plus the `CacheStalenessMiddleware`/`BackgroundRefresher` wiring, and a second PR for the `FileCacheMiddleware` side. Per repository convention, documentation updates go in separate commits from tests and implementation.

Documentation to update: `docs/guides/tent/middlewares.md` (`CacheStalenessMiddleware` option list and the `FileCacheMiddleware` bullet list), and `docs/guides/tent/cache-configuration.md`, which already instructs keeping header-filter options aligned between the two middlewares on the same `location` — the same paragraph should gain `evictOn`.

`CacheCleanupMiddleware` is deliberately untouched. It invalidates on mutating requests; this change invalidates on authorization failure. The two are complementary and neither subsumes the other.

## Testing

Run with `docker compose run --rm tent_tests composer tests:unit`, or a single file with `docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/path/to/TestFile.php`. Style via `docker compose run --rm tent_tests composer lint`.

`EvictionPolicyTest` — new file at `source/tests/unit/lib/content/EvictionPolicyTest.php`:

1. `testEmptyPolicyNeverEvicts`
2. `testExactIntegerMatchesOnlyItself`
3. `testWildcardPatternMatchesWholeFamily`
4. `testBuildUsesProvidedDefaultWhenNull`
5. `testBuildKeepsExplicitEmptyListInsteadOfDefault`
6. `testIsEmptyIsTrueForEmptyList`
7. `testIsEmptyIsFalseForExactInteger`

`source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareBuildTest.php` — existing file, extend it. It already asserts private properties via `\ReflectionClass`, so the new cases follow `testBuildParsesMaxAgeSeconds`:

1. `testBuildDefaultsEvictOnToDisabled`
2. `testBuildParsesEvictOnCamelCase`
3. `testBuildParsesEvictOnSnakeCase`
4. `testBuildPrefersCamelCaseWhenBothProvided`
5. `testBuildParsesEvictOnWildcardPattern`
6. `testBuildWithEmptyEvictOnDisablesEviction`
7. `testBuildWithNonArrayEvictOnThrows`
8. `testBuildWithInvalidEvictOnEntryThrows`

`source/tests/unit/lib/middlewares/CacheStalenessMiddleware/CacheStalenessMiddlewareProcessRequestTest.php` — existing file, extend it. The private helper `buildMiddleware()` takes only `$httpClient` and `$maxAgeSeconds`; add a sibling helper that also passes an `EvictionPolicy` to the constructor:

1. `testStaleCacheWithEvictionCodeRemovesBodyAndMeta`
2. `testStaleCacheWithEvictionCodeStillServesUnmodifiedResponse`
3. `testStaleCacheWithNonEvictionCodeKeepsStaleEntry`
4. `testStaleCacheWithDisabledPolicyKeepsEveryNonSuccessResponse`
5. `testStaleCacheWithWildcardPolicyRemovesFourXXEntry`
6. `testStaleCacheRefreshExceptionKeepsEntryRegardlessOfPolicy`
7. `testSentinelIsRemovedAfterEviction`
8. `testDebouncePreventsSecondEvictionWhileFirstInFlight`
9. `testEvictionLogsWarnMessageContainingHttpCode`
10. `testEvictionUsesMemoizedHashFromCustomHasher`

`BackgroundRefresherTest` — new file at `source/tests/unit/lib/service/BackgroundRefresherTest.php`, exercising `run()` directly with a mocked `HttpClientInterface` and a `FileCache` on a temporary directory:

1. `testSuccessfulResponseReplacesEntry`
2. `testEvictionCodeRemovesEntry`
3. `testNonEvictionCodeKeepsEntry`
4. `testExceptionIsCaughtAndEntryKept`
5. `testNullHeaderFilterStoresHeadersAsIs`
6. `testConfiguredHeaderFilterIsAppliedOnReplace`

`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareBuildTest.php` — existing file, extend it:

1. `testBuildDefaultsEvictOnToDisabled`
2. `testBuildParsesEvictOnOverride`
3. `testBuildWithEmptyEvictOnDisablesEviction`

`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessRequestTest.php` — existing file, extend it:

1. `testStoredFourXXEntryIsEvictedAndTreatedAsMissWhenPolicyMatches`
2. `testStoredTwoHundredEntryIsServedNormally`
3. `testEvictedEntryRemovesBodyAndMetaFiles`
4. `testDefaultPolicyServesStoredFourXXEntry`

`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareProcessResponseTest.php` — existing file, extend it:

1. `testFourXXResponseIsNotStoredWhenPolicyMatches`
2. `testFourXXResponseRemovesExistingEntry`
3. `testTwoHundredResponseIsStoredNormally`
4. `testDisabledPolicyStoresFourXXWhenMatcherAllows`

`source/tests/unit/lib/middlewares/FileCacheMiddleware/FileCacheMiddlewareSymmetryTest.php` — existing file, extend it with one case asserting that a `4xx` evicted on write is also absent on the subsequent read for the same key.

All test classes must call `Logger::setInstance(new NullLoggerInstance())` in `setUp()` and restore `new LoggerInstance()` in `tearDown()`, as the existing suites do. Cases covering the sentinel need a `FileSystemUtils::removeDirRecursive()` teardown over a `sys_get_temp_dir()` path.

## Context

Originating issue: https://github.com/darthjee/tent/issues/306, labelled `Feature`, `shipit` and `Writting`, opened by darthjee. Status `Backlog`, no assignees, no milestone, no comments.

Affected modules: `Tent\Middlewares\CacheStalenessMiddleware`, `Tent\Service\BackgroundRefresher`, `Tent\Middlewares\FileCacheMiddleware`, plus the new `Tent\Content\EvictionPolicy`.

Related code: `Tent\Content\FileCache`, `Tent\Service\ResponseCacher`, `Tent\Utils\HttpCodeMatcher`, `Tent\Content\HeaderFilterBuilder`.

Reference documentation:

- [Architecture](https://github.com/darthjee/tent/blob/main/docs/agents/architecture.md)
- [Creating Middlewares](https://github.com/darthjee/tent/blob/main/docs/creating-middlewares.md)
- [Middlewares guide](https://github.com/darthjee/tent/blob/main/docs/guides/tent/middlewares.md)
- [Cache Configuration](https://github.com/darthjee/tent/blob/main/docs/guides/tent/cache-configuration.md)

Upstream consumer: darthjee/citadel#33, which caches per caller keyed on the access-token cookie and currently works around the gap by skipping the cache for expired tokens.
