# Issue: Add Option to Disable Cache for Default Proxy

## Description

The default proxy in Tent has caching enabled by default (both reading and writing). This is inconvenient for applications using Tent in development mode, where stale cached responses hinder the development workflow.

## Problem

- The default proxy always sets up and activates the cache, with no way to turn it off.
- In development mode, cached responses can mask changes, making it harder to see live updates.
- There is no builder option to disable the cache when constructing the default proxy.

## Expected Behavior

- The default proxy builder should expose an option to disable the cache.
- When the option is set, both cache reads and cache writes should be skipped entirely.

## Solution

- Add a `disable_cache` (or equivalent) option to the default proxy builder.
- When this option is enabled, bypass both the cache-read and cache-write steps in the request lifecycle.

## Benefits

- Developers can run Tent in development mode without worrying about cached responses.
- Makes the default proxy configuration more flexible for different environments.

---
See issue for details: https://github.com/darthjee/tent/issues/227
