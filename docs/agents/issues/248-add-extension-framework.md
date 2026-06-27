# Issue: Add Extension Framework

## Description
Tent (the Docker image) has no built-in mechanism for users to extend it with custom PHP classes. Users who need to add matchers, middlewares, or handlers beyond what tent ships with must fork the source — there is no clean extension point.

## Problem
When tent is used via Docker, all PHP source lives inside the container image at `/var/www/html/`. There is no supported way to inject additional PHP classes (e.g. custom matchers or middlewares) without patching the image itself or duplicating the configuration layer.

## Expected Behavior
A user who wants to extend tent can:
1. Create an `extension/` folder locally containing a `loader.php` that requires their custom classes.
2. Mount that folder into the container at `/var/www/html/extension`.
3. Have tent automatically include that loader (after all core classes are available) so their classes are usable in `configuration/configure.php`.

## Solution
- Add `source/source/extension/loader.php` containing only `<?php` (plus an optional comment explaining it is the extension entry point).
- At the end of `source/source/loader.php`, add a `require_once` for the extension loader — placed after all core classes are loaded and before `configuration/configure.php` runs in `index.php`.
- Document the extension mechanism in `docs/HOW_TO_USE_DARTHJEE-TENT.md`.
- Add a minimum version line at the very top of `docs/HOW_TO_USE_DARTHJEE-TENT.md` (before the table of contents), updated by `scripts/bump_version.sh`.
- Update `scripts/bump_version.sh` to also bump the minimum version string in `docs/HOW_TO_USE_DARTHJEE-TENT.md`.
- No tests required — this is a structural addition with no logic to unit-test.

## Benefits
- Users can add custom PHP classes to tent without forking or rebuilding the image — a single Docker volume mount is enough.
- The extension point is opt-in: the default no-op loader means existing deployments are unaffected.
