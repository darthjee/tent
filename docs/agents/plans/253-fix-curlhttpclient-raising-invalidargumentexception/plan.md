# Plan: Fix CurlHttpClient Raising InvalidArgumentException

Issue: [253-fix-curlhttpclient-raising-invalidargumentexception.md](../issues/253-fix-curlhttpclient-raising-invalidargumentexception.md)

## Overview

`CurlHttpClient::request()` throws `InvalidArgumentException` for unsupported HTTP methods but lacks the required `use` import, causing PHP to look for `Tent\Http\InvalidArgumentException` instead of the built-in `\InvalidArgumentException`. The fix is a one-line import addition plus a unit test covering the invalid-method code path.

## Context

In `source/source/lib/http/CurlHttpClient.php` (namespace `Tent\Http`), the `match` expression on line 52 has a `default` branch:

```php
default => throw new InvalidArgumentException("Unsupported HTTP method: $method"),
```

Because PHP resolves unqualified class names relative to the current namespace, `InvalidArgumentException` resolves to `Tent\Http\InvalidArgumentException`, which does not exist. A fatal `Error` is thrown instead of the intended `\InvalidArgumentException`. There is currently no test covering this branch.

## Implementation Steps

### Step 1 — Add the missing `use` import in `CurlHttpClient.php`

Add `use InvalidArgumentException;` to the import block at the top of `source/source/lib/http/CurlHttpClient.php`, alongside the existing `use` statements. This imports the global built-in class into the `Tent\Http` namespace scope so the bare name resolves correctly.

### Step 2 — Add a unit test for the invalid-method path

Create `source/tests/unit/lib/http/CurlHttpClient/CurlHttpClientInvalidMethodTest.php` in the existing `CurlHttpClient/` test folder. The test should:

- Instantiate `CurlHttpClient`.
- Call `request('INVALID', 'http://example.com', [])`.
- Assert that `\InvalidArgumentException` is thrown.
- Assert that the exception message contains `"Unsupported HTTP method: INVALID"` (or a similar substring matching the actual message).

Follow the same namespace (`Tent\Tests\Http`), `require_once` loader path, and class structure used in the existing test files in that folder (e.g. `CurlHttpClientGetTest.php`).

## Files to Change

- `source/source/lib/http/CurlHttpClient.php` — add `use InvalidArgumentException;` import
- `source/tests/unit/lib/http/CurlHttpClient/CurlHttpClientInvalidMethodTest.php` — new test file covering the `default` branch of the `match` expression

## CI Checks

- `source/`: `composer coverage` (CI job: `source_tests`)
- `source/`: `composer lint` (CI job: `source_lint`)

## Notes

- The built-in `\InvalidArgumentException` is the correct class to use here (it extends `\LogicException` from the PHP SPL). No custom exception class needs to be created.
- The test does not need a real HTTP server — the exception is thrown before any network call.
- Using `$this->expectException(\InvalidArgumentException::class)` in PHPUnit is sufficient; no mock is needed.
