# Documentation
Document `evictOn` / `evict_on` for both middlewares, state that the default is disabled, that exceptions never evict, and that both middlewares on the same `location` should use matching lists (like the header-filter options). Commit separately from code and tests.

## Files to Change
- `docs/guides/tent/middlewares.md` — `CacheStalenessMiddleware` option list and `FileCacheMiddleware` bullet list.
- `docs/guides/tent/cache-configuration.md` — add `evictOn` to the paragraph about keeping options aligned between the two middlewares.
