# Plan: Add Logger and Logger Level

## Overview

Introduce a logging abstraction to Tent consisting of three classes under `source/source/lib/log/`:

- `Logger` — static facade; all public entry points live here.
- `LoggerInstance` — default implementation that reads `LOG_LEVEL` and writes to the console.
- `NullLoggerInstance` — silent implementation used as a test double.

## Context

Tent currently has no structured logger. Log output is uncontrolled and cannot be filtered by severity. Tests that touch code paths with logging produce noise and cannot assert on log calls. The `Logger` static facade solves both: production code calls `Logger::error(...)` etc., and tests can either call `Logger::disable()` or swap the instance for a `NullLoggerInstance`.

## Implementation Steps

### Step 1 — Create `LoggerInstance`

Create `source/source/lib/log/LoggerInstance.php`.

- Constructor reads `LOG_LEVEL` from the environment (default: `debug` — log everything).
- Internal `log(string $message, string $level): void` compares `$level` against the threshold and writes to stdout/stderr when the level is sufficient.
- Level precedence map: `debug=0, info=1, warn=2, error=3`.
- Exposes `enable(): void` and `disable(): void` to override level filtering entirely.

### Step 2 — Create `NullLoggerInstance`

Create `source/source/lib/log/NullLoggerInstance.php`.

- Implements the same interface / extends `LoggerInstance`.
- All methods (`log`, `enable`, `disable`) are no-ops.
- Used in tests via `Logger::setInstance(new NullLoggerInstance())`.

### Step 3 — Create `Logger` static facade

Create `source/source/lib/log/Logger.php`.

- Holds a private static `LoggerInstance` reference (initialized to a default `LoggerInstance` on first use).
- Public static methods:

| Method | Delegates to |
|--------|-------------|
| `debug(string $message): void`  | `instance->log($message, 'debug')` |
| `info(string $message): void`   | `instance->log($message, 'info')`  |
| `warn(string $message): void`   | `instance->log($message, 'warn')`  |
| `error(string $message): void`  | `instance->log($message, 'error')` |
| `setInstance(LoggerInstance $instance): void` | replaces static reference |
| `enable(): void`  | `instance->enable()`  |
| `disable(): void` | `instance->disable()` |

### Step 4 — Register classes in `loader.php`

Add `require_once` entries to `source/source/loader.php` in dependency-first order:

1. `lib/log/LoggerInstance.php`
2. `lib/log/NullLoggerInstance.php`
3. `lib/log/Logger.php`

### Step 5 — Write unit tests

Add tests under `source/tests/unit/log/`:

- `LoggerInstanceTest` — verify level filtering for all combinations of message level vs `LOG_LEVEL`; verify `disable()` suppresses all output; verify `enable()` restores it.
- `LoggerTest` — verify each static method calls through to the instance; verify `setInstance()` swaps the instance; verify `enable()`/`disable()` delegate correctly.

### Step 6 — Update documentation

- `README.md` — add `LOG_LEVEL` to the environment variables section (accepted values: `debug`, `info`, `warn`, `error`; default: `debug`).
- `docs/agents/architecture.md` — add `log/` subdirectory to the source layout and a row in the Key Components table.
- `dockerhub_description.md` — mention `LOG_LEVEL` in the configuration reference.

## Files to Change

- New: `source/source/lib/log/LoggerInstance.php`
- New: `source/source/lib/log/NullLoggerInstance.php`
- New: `source/source/lib/log/Logger.php`
- New: `source/tests/unit/log/LoggerInstanceTest.php`
- New: `source/tests/unit/log/LoggerTest.php`
- Updated: `source/source/loader.php`
- Updated: `README.md`
- Updated: `docs/agents/architecture.md`
- Updated: `dockerhub_description.md`

## Notes

- `NullLoggerInstance` may extend `LoggerInstance` and override `log()` to be a no-op, or both may implement a shared interface — to be decided when reading the code.
- Tests should set `LOG_LEVEL` via `putenv()` / `$_ENV` so the env variable does not leak between test cases.
- `Logger::setInstance()` should also be called in `tearDown()` to restore the default instance after each test that swaps it.
