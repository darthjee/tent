# Plan: Add custom cache hash generator

Issue: [260-add-custom-cache-hash-generator.md](../../issues/260-add-custom-cache-hash-generator.md)

## Overview

Introduce a pluggable `Tent\Cache\RequestHasher` contract (`hash(RequestInterface $request): string` + `build(array $params): self`) that developers can configure on `FileCacheMiddleware` and `DefaultProxyRequestHandler` via a new `request_hasher` config key, following the project's existing `class`-key strategy pattern (same as matchers). The computed hash is memoized on `ProcessingRequest` (`cacheHash()`/`setCacheHash()`) so it is computed at most once per request, and `CacheStalenessMiddleware` transparently reuses it without needing its own configuration. Ships with a default `QueryRequestHasher` that preserves today's query-only hashing behavior. Documentation gets a new guide for writing custom hashers, including the security guidance that sensitive request data must be hashed by the developer, not embedded raw.

## Agents involved

- [tent](tent.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

- **`Tent\Cache\RequestHasher` interface** (new file, `source/source/lib/cache/RequestHasher.php`):
  ```php
  namespace Tent\Cache;

  use Tent\Models\RequestInterface;

  interface RequestHasher
  {
      public function hash(RequestInterface $request): string;
      public static function build(array $params): self;
  }
  ```
- **`Tent\Cache\QueryRequestHasher`** (new file, `source/source/lib/cache/QueryRequestHasher.php`) — default implementation, used when no `request_hasher` is configured. Returns `hash('sha256', $request->query())`. `build(array $params): self` ignores `$params` and returns `new self()`.
- **`request_hasher` config key** on `FileCacheMiddleware` and `DefaultProxyRequestHandler`, shaped exactly like the existing matcher convention:
  ```php
  'request_hasher' => [
      'class' => 'Fully\\Qualified\\ClassName',
      // ...any extra keys that class's own build() reads
  ]
  ```
  Built via `$attributes['request_hasher']['class']::build($attributes['request_hasher'])`. Omitting the key defaults to `QueryRequestHasher`.
- **No `request_hasher` key on `CacheStalenessMiddleware`** — it must keep relying on `ProcessingRequest::cacheHash()` already being memoized by `FileCacheMiddleware` earlier in the same request. `product-dev-extending`'s docs must not document a `request_hasher` option on `CacheStalenessMiddleware`.
- **Developer-facing contract for docs**: a custom `RequestHasher` receives the full `RequestInterface` (including headers), and is fully responsible for producing a filesystem-safe string and for hashing (e.g. SHA-256) any sensitive request data itself — Tent performs no sanitization or validation of the returned value.
