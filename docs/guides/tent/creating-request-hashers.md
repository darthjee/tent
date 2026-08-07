# Creating Request Hashers

Tent derives the on-disk cache key used by `FileCacheMiddleware` from a `RequestHasher`. By default, only the request's query string is hashed (`Tent\Cache\QueryRequestHasher`), preserving Tent's historical cache-key behaviour — but you can plug in a custom hasher to key cache entries on additional request data (headers, an auth token, a tenant identifier, etc.).

## Table of Contents

- [What Is a `RequestHasher`?](#what-is-a-requesthasher)
- [RequestHasher Interface](#requesthasher-interface)
- [How to Create a Custom RequestHasher](#how-to-create-a-custom-requesthasher)
- [Security](#security)
- [Edge Cases](#edge-cases)
- [Performance](#performance)
- [Configuration](#configuration)
- [Best Practices](#best-practices)

---

## What Is a `RequestHasher`?

A `RequestHasher` is a small strategy object used by `Tent\Middlewares\FileCacheMiddleware` — directly, or indirectly via `Tent\RequestHandlers\DefaultProxyRequestHandler`, which builds a `FileCacheMiddleware` internally — to compute the digest used to name a request's cache files on disk. Tent invokes it at most once per request (see [Performance](#performance)) and uses the returned string, unmodified, as part of the cache file path.

## RequestHasher Interface

All request hashers implement `Tent\Cache\RequestHasher`:

```php
namespace Tent\Cache;

use Tent\Models\RequestInterface;

interface RequestHasher
{
    public function hash(RequestInterface $request): string;

    public static function build(array $params): self;
}
```

### `hash(RequestInterface $request): string`

Receives the full request — method, path, query string, body, uploaded files, and **headers** — and must return a string to use as the cache-key hash for that request. Two requests that should share a cache entry must return the same string; two requests that must be cached separately must return different strings.

### `build(array $params): self`

Static factory used when the hasher is configured via a `request_hasher` array in a rule (see [Configuration](#configuration)). Receives that same array, including the `class` key, and must return a constructed instance.

---

## How to Create a Custom RequestHasher

### 1. Implement the Interface

```php
namespace Tent\Cache;

use Tent\Models\RequestInterface;

class HeaderAwareRequestHasher implements RequestHasher
{
    private string $headerName;

    public function __construct(string $headerName = 'X-Tenant-Id')
    {
        $this->headerName = $headerName;
    }

    public function hash(RequestInterface $request): string
    {
        $headers = array_change_key_case($request->headers(), CASE_LOWER);
        $tenant = $headers[strtolower($this->headerName)] ?? 'default';

        return hash('sha256', $tenant . '|' . $request->query());
    }

    public static function build(array $params): self
    {
        return new self($params['headerName'] ?? 'X-Tenant-Id');
    }
}
```

### 2. Load It

Custom `RequestHasher` classes are wired the same way as custom middlewares and matchers — via `/var/www/html/extension/loader.php` (see [Extending Tent](extending-tent.md)):

```php
<?php

require_once __DIR__ . '/HeaderAwareRequestHasher.php';
```

### 3. Reference It in Configuration

See [Configuration](#configuration) below.

---

## Security

`hash()` receives the **full request**, including headers — this is intentional. It lets you key cache entries on data the query string alone doesn't capture, such as an auth token or a tenant identifier, so private/authenticated responses can still be cached safely, per caller.

Tent does not treat the returned string as secret, and does not encrypt the cache directory contents. If your hash embeds sensitive data (an auth token, a session id, a credential) raw, that value ends up readable in a filename/directory on disk. Always hash sensitive data yourself before returning it:

```php
// Good: sensitive token is hashed
return hash('sha256', $token);

// Also good: a static, non-sensitive prefix for readability/namespacing,
// with the sensitive part still hashed
return 'private_' . hash('sha256', $token);

// Bad: raw token embedded in the returned string
return 'private_' . $token;
```

A static, non-sensitive prefix (e.g. `'private_'`, a route name) is fine to leave unhashed for readability — it's the sensitive portion of the request (tokens, credentials, PII) that must always be hashed, never embedded raw.

## Edge Cases

Tent performs **no validation or sanitization** on the string returned by `hash()`. In particular:

- No length limit is enforced.
- No filesystem-safety check is performed (e.g. path separators, null bytes, or characters invalid on the host filesystem).
- No collision check is performed — if two different requests produce the same hash, they will share (and clobber) the same cache entry.

An unsafe return value is not caught by Tent — it flows straight through to the underlying file operations, which will fail or misbehave according to the host filesystem's own rules (e.g. `mkdir`/`file_put_contents` erroring on an invalid path segment). Stick to a fixed-length, filesystem-safe output — hashing algorithms like `sha256`/`md5` naturally satisfy this, which is why the built-in `QueryRequestHasher` and the example above use one.

## Performance

The computed hash is memoized on the request (`ProcessingRequest::cacheHash()` / `setCacheHash()`), so `hash()` runs **at most once per request**, even though both `FileCacheMiddleware::processRequest()` (cache read) and `processResponse()` (cache write) construct a `FileCache` internally. `CacheStalenessMiddleware`, when paired with a preceding `FileCacheMiddleware`, transparently reuses that same memoized hash — it has no `request_hasher` option of its own.

That said, `hash()` still runs once on **every** cache-eligible request, so keep it cheap: prefer a single pass over headers/query data and a fast hashing algorithm (`sha256` is fine) over anything that performs I/O, calls out to another service, or does expensive parsing.

## Configuration

Configure a custom hasher with a `request_hasher` array — a `class` key (fully-qualified class name) plus any options your `build()` method reads. It works the same way on a manual `FileCacheMiddleware` entry and on `default_proxy`'s handler options.

### On `FileCacheMiddleware`

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'request_hasher' => [
                'class' => 'Tent\\Cache\\HeaderAwareRequestHasher',
                'headerName' => 'X-Tenant-Id'
            ]
        ]
    ]
]);
```

### On `default_proxy`

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
        'request_hasher' => [
            'class' => 'Tent\\Cache\\HeaderAwareRequestHasher',
            'headerName' => 'X-Tenant-Id'
        ]
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

Omitting `request_hasher` keeps the default `Tent\Cache\QueryRequestHasher` behaviour (hash of the query string only).

---

## Best Practices

- **Hash, don't embed**: any sensitive request data must go through a hashing function before being returned — see [Security](#security).
- **Keep it deterministic**: the same logical request must always produce the same hash, or cache hits will never occur.
- **Keep it cheap**: it runs on every cache-eligible request — see [Performance](#performance).
- **Use a fixed-length, filesystem-safe output** (e.g. `hash('sha256', ...)`) — see [Edge Cases](#edge-cases).
- **Don't configure `request_hasher` on `CacheStalenessMiddleware`**: it has no such option and transparently reuses the hash already computed by the paired `FileCacheMiddleware`.
