# Issue: Add Logger and Logger Level

## Description

Tent currently lacks a dedicated logging abstraction. A `Logger` class should be introduced in `source/source/lib/` as a static facade that delegates to an injectable `LoggerInstance`, centralizing all log output and supporting configurable log levels.

## Problem

- There is no structured logger class; log output is uncontrolled.
- Log verbosity cannot be tuned at runtime via an environment variable.
- There is no single place to apply log-level filtering logic.
- Tests that exercise code paths with logging produce unwanted console noise and cannot assert on log calls.

## Expected Behavior

### `Logger` — static facade

All methods are `static`. The class holds a reference to the active `LoggerInstance` and forwards every call to it.

| Method | Signature | Notes |
|--------|-----------|-------|
| `debug` | `static debug(string $message): void` | Lowest priority |
| `info`  | `static info(string $message): void`  | |
| `warn`  | `static warn(string $message): void`  | |
| `error` | `static error(string $message): void` | Highest priority |
| `setInstance` | `static setInstance(LoggerInstance $instance): void` | Replaces the active instance; used in tests |
| `enable`      | `static enable(): void`  | Delegates to the active instance |
| `disable`     | `static disable(): void` | Delegates to the active instance |

### `LoggerInstance` — default implementation

Exposes `enable()` and `disable()` instance methods to override level-based filtering entirely — when disabled, no message is written regardless of `LOG_LEVEL`.

Reads the `LOG_LEVEL` environment variable on each call and writes to the console only when the message's level meets or exceeds the configured threshold.

Level precedence (ascending): `debug < info < warn < error`.

### `NullLoggerInstance` (or similar) — test double

An implementation of `LoggerInstance` that discards all messages. Tests call `Logger::setInstance(new NullLoggerInstance())` in `setUp()` and restore the default in `tearDown()`.

> **Note:** `Logger::disable()` is an alternative when swapping the instance is not desirable — it silences the current instance for the duration of the test. `Logger::enable()` restores output.

## Solution

1. Create `source/source/lib/Logger.php` with the four static level methods and `setInstance()`.
2. Create `source/source/lib/LoggerInstance.php` — the default instance that reads `LOG_LEVEL` and writes to the console.
3. Create `source/source/lib/NullLoggerInstance.php` — silent instance for tests.
4. Add `require_once` entries for all three files to `source/source/loader.php`.
5. Update `README.md`, `docs/agents/architecture.md`, and `dockerhub_description.md` to document the `LOG_LEVEL` env variable and its accepted values (`debug`, `info`, `warn`, `error`).

## Benefits

- Developers can suppress verbose debug output in production by setting `LOG_LEVEL=error`.
- Tests can inject a silent instance, keeping output clean and enabling log-call assertions.
- All logging goes through one place, making it easy to swap output targets later.
- Consistent log format across the entire proxy.

---
See issue for details: https://github.com/darthjee/tent/issues/201
