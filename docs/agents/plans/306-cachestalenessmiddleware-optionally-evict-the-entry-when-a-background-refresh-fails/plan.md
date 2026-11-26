# Plan: CacheStalenessMiddleware: optionally evict the entry when a background refresh fails

Issue: [306-cachestalenessmiddleware-optionally-evict-the-entry-when-a-background-refresh-fails.md](../../issues/306-cachestalenessmiddleware-optionally-evict-the-entry-when-a-background-refresh-fails.md)

## Overview
Add an opt-in `evictOn` status list (integers or wildcards such as `4xx`) shared by `CacheStalenessMiddleware` (on a failed background refresh) and `FileCacheMiddleware` (read and write paths) through a new `EvictionPolicy`. Default is `[]` on both, so existing behaviour is unchanged.

See [tent.md](tent.md) for the full plan.
