# product-dev-extending Plan: Add custom cache hash generator

Main plan: [plan.md](plan.md)

## Shared contracts

- Can rely on: `Tent\Cache\RequestHasher` interface (`hash(RequestInterface $request): string`, `build(array $params): self`) and `Tent\Cache\QueryRequestHasher` default, both implemented by `tent`.
- Can rely on: `request_hasher` config key on `FileCacheMiddleware` and `DefaultProxyRequestHandler`, shaped as `['class' => 'Fully\\Qualified\\ClassName', ...]`, built via that class's own `build()`.
- Must NOT document a `request_hasher` option on `CacheStalenessMiddleware` — it has none; it transparently reuses the hash `FileCacheMiddleware` already computed for the same request.
- Must state clearly: `RequestHasher::hash()` receives the full request (including headers), and Tent performs no sanitization/validation on its return value — producing a filesystem-safe string and hashing (e.g. SHA-256) any sensitive request data is entirely the developer's responsibility.

## Implementation Steps

### Step 1 — Add a "Creating Request Hashers" guide

Add a new top-level doc, `docs/creating-request-hashers.md`, mirroring the structure and tone of `docs/creating-middlewares.md` (What is a `RequestHasher`? / Interface / How to Create a Custom One / Configuration / Best Practices). Cover:

- The interface contract (`hash(RequestInterface $request): string`, `build(array $params): self`).
- A minimal example implementing `RequestHasher`.
- **Security section**: the hasher receives the full request, so it can key cache entries on headers (e.g. an auth token) to safely cache private/authenticated responses — but it must hash sensitive data itself (e.g. `hash('sha256', ...)`) rather than embedding it raw in the returned string. A static, non-sensitive prefix is fine for readability/namespacing (e.g. `"private_" . hash('sha256', $token)`), but the sensitive part must always be hashed.
- **Edge cases section**: the returned string is trusted as-is — no sanitization, length checks, or filesystem-safety validation is performed by Tent. An unsafe return value causes the underlying file operation to fail/error naturally.
- **Performance note**: the hasher runs at most once per request (the result is memoized), but keep it cheap — it still runs on every cache-eligible request.
- Configuration example showing `request_hasher` on both `FileCacheMiddleware` and `default_proxy`'s `DefaultProxyRequestHandler`.

### Step 2 — Wire the new guide into navigation

Add a link to `docs/creating-request-hashers.md` in `docs/HOW_TO_USE_DARTHJEE-TENT.md`'s Table of Contents, near the existing `Middlewares`/`Cache Configuration` entries, and cross-link it from `docs/creating-middlewares.md` and `docs/tent/cache-configuration.md` wherever they currently discuss cache key generation.

### Step 3 — Document `request_hasher` in `docs/tent/cache-configuration.md`

Add a new `## Custom cache hash generator` section (after the existing "Manual `FileCacheMiddleware` setup" section) showing `request_hasher` configured on both `default_proxy`'s `cache`/`request_hasher` options and a manual `FileCacheMiddleware` entry, with a one-line pointer to `docs/creating-request-hashers.md` for the full guide. Reuse the file's existing example style (fenced `Configuration::buildRule()` snippets).

### Step 4 — Document `request_hasher` in `docs/creating-middlewares.md`'s `FileCacheMiddleware` entry

Under the existing `### FileCacheMiddleware` section, add a short mention of the new `request_hasher` option alongside `location`/`matchers`, with a pointer to `docs/creating-request-hashers.md`.

### Step 5 — Verify extensibility end-to-end

Following this agent's usual remit (verifying a developer using the proxy can actually extend it as documented): write and run a small custom `RequestHasher` against the dev/test setup exactly as the new guide describes it, confirming the documented `request_hasher` config actually produces the described behavior (cache entries keyed on the custom hash) before considering the docs done.

## Files to Change

- `docs/creating-request-hashers.md` — new guide.
- `docs/HOW_TO_USE_DARTHJEE-TENT.md` — add Table of Contents entry.
- `docs/tent/cache-configuration.md` — add `## Custom cache hash generator` section.
- `docs/creating-middlewares.md` — add `request_hasher` mention under `FileCacheMiddleware`.

## Notes

- Depends on `tent`'s work for the actual class names/config key to be correct in examples — do not merge docs ahead of confirming the final namespace/method names match what was implemented.
- No code changes in this agent's scope; this is documentation-only, plus manual verification against the running dev setup.
