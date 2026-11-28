# Add a PHP-warning capturing test helper

Add a small test-support class that records PHP warnings and notices raised while it is active. This lets the "no warning" regression be asserted explicitly, without relying on PHPUnit 9's `convertWarningsToExceptions` (removed in PHPUnit 10+).

Suggested shape: `Tent\Tests\Support\Utils\PhpWarningCatcher`:

- `start()` installs `set_error_handler()` (for `E_ALL`) and `stop()` restores it (`restore_error_handler()`). Tests call `stop()` in `tearDown()`.
- `warnings(): array` returns the captured `[errno, message]` pairs.
- **It must ignore suppressed errors**: when `!(error_reporting() & $errno)` (the `@` operator in PHP 8), return `false` without recording. That way `@`-silenced calls are not counted, and PHP still populates `error_get_last()`, which the mkdir backstop relies on.
- For recorded errors, return `true`, so they neither reach PHPUnit's handler nor get printed.

Register it in `tests/support/loader.php` if that loader lists support files explicitly.

## Files to Change
- `source/tests/support/utils/PhpWarningCatcher.php` — new helper.
- `source/tests/support/loader.php` — require the new helper (if needed).
