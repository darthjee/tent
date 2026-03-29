# Issue 190: Remove unused private methods and variables

Issue Link https://github.com/darthjee/navi/issues/98

## Summary

Private methods and variables that are never used represent dead code: they increase
cognitive load, mislead readers, and can mask real bugs. They must be removed from all
code, including test files.

Additionally, the existing lint tooling (PHPCS + PHPMD) does not run PHPMD unused-code
checks against the `tests/` directory, so violations there go undetected in CI. A new
focused check must be added.

## Violations found

| File | Type | Member |
|------|------|--------|
| `tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerCachedTest.php` | private field | `$httpClient` |
| `tests/unit/lib/request_handlers/DefaultProxyRequestHandler/DefaultProxyRequestHandlerCachedTest.php` | private method | `expectedHeadersAfterDefaultMiddlewares()` |
| `tests/unit/lib/service/ResponseCacherTest.php` | private field | `$cache` |

## Acceptance criteria

- All unused private fields and methods listed above are removed.
- A new PHPMD ruleset `phpmd_unusedcode.xml` is added to enforce `UnusedPrivateField`
  and `UnusedPrivateMethod` rules.
- A new `lint:unused` composer script runs PHPMD with this ruleset against both `source`
  and `tests`.
- The `checks` CI job runs `composer lint:unused` and fails the build when violations
  are present.
- All lint and test checks pass after the changes.
