# Issue: Add Logger and Logger Level

## Description

Tent currently lacks a dedicated logging abstraction. A `Logger` class (or equivalent) should be introduced in `source/` to centralize all log output and support configurable log levels.

## Problem

- There is no structured logger class; log output is uncontrolled.
- Log verbosity cannot be tuned at runtime via an environment variable.
- There is no single place to apply log-level filtering logic.

## Expected Behavior

- A `Logger` class exists with one method per log level: `debug()`, `error()`, `warn()`, etc.
- Each level-specific method delegates to a single internal method, passing the message and the level.
- The internal method compares the requested level against the `LOG_LEVEL` environment variable and only writes to the console when the level is at or above the configured threshold.

## Solution

- Create `source/Logger.php` (or equivalent namespace path) with the level methods and a private `log(string $message, string $level): void` method.
- Read `LOG_LEVEL` from the environment to determine the minimum level to output.
- Define level precedence (e.g., `debug < info < warn < error`) so filtering works correctly.
- Update `README.md`, `docs/agents/` files, and `dockerhub_description.md` to document the new env variable and its accepted values.

## Benefits

- Developers can suppress verbose debug output in production by setting `LOG_LEVEL=error`.
- All logging goes through one place, making it easy to swap output targets later.
- Consistent log format across the entire proxy.

---
See issue for details: https://github.com/darthjee/tent/issues/201
