# Issue: Fix Logger

## Description

The logger implementation in the `source` folder is currently using `echo` to write log output instead of the proper logging tool. Logs should be written through the designated logging mechanism so they are captured alongside Apache logs.

## Problem

- The logger uses `echo` to produce log output.
- Log entries are not written through the proper Apache logging facility.
- This means log output is not consolidated with Apache logs, making debugging and log aggregation harder.

## Expected Behavior

- The logger should use the appropriate PHP/Apache logging tool (e.g., `error_log`) to write log entries.
- Log output should appear alongside Apache logs, enabling consistent log management.

## Solution

- Replace `echo` calls in the logger implementation (under `source/`) with the correct logging tool.
- Ensure the output is directed to the Apache log stream.

## Benefits

- Consistent log aggregation alongside Apache logs.
- Easier debugging and monitoring of Tent in production and development environments.

---
See issue for details: https://github.com/darthjee/tent/issues/208
