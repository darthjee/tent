# Plan: Fix Logger

## Overview

Replace `echo` calls in `LoggerInstance` with the proper PHP logging tool (`error_log`) so that log output is written to the Apache error log stream instead of stdout.

## Context

The logger facade (`Logger`) delegates to `LoggerInstance`, which currently uses `echo` to emit log messages. This means logs are written to stdout and are not captured by Apache's logging facility. The fix is to replace `echo` with `error_log`, which writes to the Apache error log (stderr in the Docker container), making all Tent log entries visible alongside Apache logs.

## Implementation Steps

### Step 1 — Replace `echo` with `error_log` in `LoggerInstance`

In `source/source/lib/log/LoggerInstance.php`, replace every `echo` call used to emit log messages with `error_log(...)`. The message format should remain unchanged; only the output mechanism changes.

### Step 2 — Verify `NullLoggerInstance` is unaffected

`NullLoggerInstance` is a silent test double and should not emit anything. Confirm it does not use `echo` and requires no changes.

### Step 3 — Update or add tests

Ensure unit tests for `LoggerInstance` verify that `error_log` is called (not `echo`) with the expected message format. If existing tests assert on `echo` output, update them accordingly.

## Files to Change

- `source/source/lib/log/LoggerInstance.php` — replace `echo` with `error_log` for log output
- `source/tests/unit/` (logger-related test file) — update assertions to match the new output mechanism

## Notes

- `error_log()` in PHP writes to the web server error log by default (Apache stderr in Docker), which is the intended destination.
- No changes needed to the `Logger` static facade or `NullLoggerInstance`.
- Log message format (`[LEVEL] message`) should be preserved as-is.
