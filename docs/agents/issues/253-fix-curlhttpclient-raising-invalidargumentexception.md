# Issue: Fix CurlHttpClient raising InvalidArgumentException

## Description
In `source/source/lib/http/CurlHttpClient.php`, the `request()` method uses a `match` expression to select an executor class based on the HTTP method. The `default` branch throws an `InvalidArgumentException` for unsupported methods, but the `InvalidArgumentException` class is not imported via a `use` statement. As a result, PHP cannot find the class and a fatal error occurs instead of the intended exception.

## Problem
At line 52 of `CurlHttpClient.php`, `throw new InvalidArgumentException(...)` references a bare class name with no `use Tent\Http\InvalidArgumentException` or `use \InvalidArgumentException` import. PHP resolves unqualified class names relative to the current namespace (`Tent\Http`), so the class is not found and a fatal `Error` is thrown rather than the intended `InvalidArgumentException`. Additionally, there is no PHPUnit test that passes an unsupported HTTP method to `CurlHttpClient::request()` to verify this exception path.

## Expected Behavior
- `CurlHttpClient::request()` should throw `\InvalidArgumentException` (the built-in PHP one from the global namespace) when called with an unsupported HTTP method.
- A unit test should cover this scenario, asserting that the correct exception type and message are produced.

## Solution
1. Add `use InvalidArgumentException;` (or use the fully-qualified `\InvalidArgumentException`) in `CurlHttpClient.php` so the throw resolves correctly.
2. Add a test case (likely in a new file such as `CurlHttpClientInvalidMethodTest.php` inside the existing `CurlHttpClient/` test folder) that calls `request()` with an unsupported method and asserts the exception.
