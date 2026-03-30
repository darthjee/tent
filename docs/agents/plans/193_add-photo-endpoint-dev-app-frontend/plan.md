# Plan: Issue 193 — Add Photo Endpoint for Dev App Frontend (Tent proxy)

## Goal

Expose `POST /persons/:id/photo` through the Tent proxy so that browser clients can upload a person's photo without knowing the backend URL convention.
Tent must forward the request to `api_dev` at `POST /persons/:id/photo.json` (issue 192).

---

## Context

- Tent routes are defined in `docker_volumes/configuration/configure.php` (or `rules/`), which is **not version-controlled**. The plan documents the required configuration and any new Tent classes that need to be shipped in the source.
- Available matchers today: `ExactRequestMatcher`, `BeginsWithRequestMatcher`, `EndsWithRequestMatcher`, `RequestMethodMatcher`, `NegativeMatcher`. None supports arbitrary URI patterns.
- `SetPathMiddleware` sets a **static** path — it cannot append a dynamic suffix.
- The frontend path (`/persons/:id/photo`) and the backend path (`/persons/:id/photo.json`) differ only by the `.json` suffix, so the proxy must append `.json` to the path before forwarding.

---

## Implementation Steps

### Step 1 — New middleware: `AppendSuffixToPathMiddleware`

**File:** `source/source/lib/middlewares/AppendSuffixToPathMiddleware.php`

A request-phase middleware that appends a fixed string to the current request path:

```php
public function processRequest(ProcessingRequest $request): ProcessingRequest
{
    $request->setRequestPath($request->requestPath() . $this->suffix);
    return $request;
}
```

Configuration key: `suffix` (string).
Factory method `build(array $attributes)` reads `$attributes['suffix']`.

Usage in configuration:

```php
[
    'class'  => 'Tent\\Middlewares\\AppendSuffixToPathMiddleware',
    'suffix' => '.json',
]
```

---

### Step 2 — Register the new middleware class

**File:** `source/source/loader.php`

Add after existing middleware requires:

```php
require_once __DIR__ . '/lib/middlewares/AppendSuffixToPathMiddleware.php';
```

---

### Step 3 — Wire the middleware in the factory

**File:** (wherever Tent resolves middleware class names to `build()` calls — locate the middleware factory/builder used in `Configuration::buildRule()`)

Add a case for `AppendSuffixToPathMiddleware` so it is instantiated with `AppendSuffixToPathMiddleware::build($attrs)`.

---

### Step 4 — Tent proxy rule (configuration documentation)

The rule below belongs in `docker_volumes/configuration/configure.php` (or a dedicated file under `docker_volumes/configuration/rules/`). Because this directory is not version-controlled, the canonical configuration must be documented here so operators can reproduce it.

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80',
    ],
    'matchers' => [
        ['type' => 'begins_with', 'uri' => '/persons/'],
        ['type' => 'ends_with',   'uri' => '/photo'],
        ['method' => 'POST'],
    ],
    'middlewares' => [
        [
            'class'  => 'Tent\\Middlewares\\AppendSuffixToPathMiddleware',
            'suffix' => '.json',
        ],
    ],
]);
```

**Matcher combination rationale:**
- `BeginsWithRequestMatcher('/persons/')` ensures only person sub-routes are matched.
- `EndsWithRequestMatcher('/photo')` restricts to the upload path (not `/photo.json`, not other sub-paths).
- `RequestMethodMatcher('POST')` prevents accidental GET/PUT matches.

The combination is an approximation of the pattern `/persons/\d+/photo`. It would also match a (hypothetical) path like `/persons/foo/bar/photo` — acceptable for now given the controlled scope of the dev application. A future `RegexRequestMatcher` (see notes below) would be more precise.

---

### Step 5 — Tests

#### 5a — `AppendSuffixToPathMiddleware` unit test

**File:** `source/tests/unit/lib/middlewares/AppendSuffixToPathMiddlewareTest.php`

Cases:
- Appends suffix to a plain path (`/persons/1/photo` → `/persons/1/photo.json`).
- Appends suffix when path already has a trailing segment.
- Empty suffix leaves path unchanged.
- Does not modify response (response-phase no-op).

#### 5b — Integration test (optional, within existing integration test suite)

Verify that a `POST /persons/1/photo` request routed through Tent reaches `api_dev` as `POST /persons/1/photo.json`. Uses the dev API in the test environment.

---

## Notes

### Future improvement: `RegexRequestMatcher`

The `begins_with` + `ends_with` matcher combination is pragmatic but imprecise. A dedicated `RegexRequestMatcher` would let operators write:

```php
['type' => 'regex', 'pattern' => '^/persons/\d+/photo$', 'method' => 'POST']
```

This is intentionally left out of scope for this issue. If needed, it should be a separate issue targeting the matcher system.

---

## Files Changed / Created

| Action | Path |
|--------|------|
| Create | `source/source/lib/middlewares/AppendSuffixToPathMiddleware.php` |
| Modify | `source/source/loader.php` |
| Modify | middleware factory (locate exact file) |
| Create | `source/tests/unit/lib/middlewares/AppendSuffixToPathMiddlewareTest.php` |
| Document | `docker_volumes/configuration/configure.php` (not version-controlled) |

---

## Commit order

1. `AppendSuffixToPathMiddleware` — new middleware + loader + factory + unit test
2. Configuration documentation update (plan file) if needed
