# Document the constraints in the middleware guide

In the `CacheCleanupMiddleware` section of the guide, add a short note (near the `custom` option description, or right after the rename-then-delete paragraph) stating:

- `custom` targets must not contain `.` or `..` segments — such targets are refused and a `warn` (`cache cleanup refused — …`) is logged;
- cache subdirectories must not be symlinks — cleanups that would resolve outside the cache location are refused with a `warn`; the cache `location` itself may be a symlink (e.g. a Docker volume).

Keep it to a few lines, matching the guide's existing tone and bullet style.

## Files to Change
- `docs/guides/tent/middlewares.md` — note on `custom` target and symlink constraints in the `CacheCleanupMiddleware` section.
