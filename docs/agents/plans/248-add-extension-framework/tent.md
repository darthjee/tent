# Tent Plan: Add Extension Framework

Main plan: [plan.md](plan.md)

## Shared contracts

This agent creates `source/source/extension/loader.php`, which maps to `/var/www/html/extension/loader.php` inside the container. Users mount a custom `loader.php` at that container path to inject custom PHP classes. The `infra` agent documents this path in the HOW_TO_USE guide.

## Implementation Steps

### Step 1 — Create `source/source/extension/loader.php`

Create the file with only a PHP open tag and a comment explaining its purpose:

```php
<?php

// Extension entry point.
// Mount your custom loader.php at /var/www/html/extension/loader.php
// to inject custom matchers, middlewares, or handlers into Tent.
// This default file is a no-op — it is replaced by the mounted volume.
```

This file is the default no-op extension loader. When a user mounts their own `loader.php` at the matching container path, it replaces this file.

### Step 2 — Add `require_once` to `source/source/loader.php`

At the very end of `source/source/loader.php` (after all existing `require_once` statements), add:

```php
require_once __DIR__ . '/extension/loader.php';
```

This ensures the extension loader runs after all core Tent classes are available, so extension classes can `use` any Tent class without worrying about load order.

The `index.php` bootstraps as:
1. `require_once loader.php` (loads all core classes + extension loader)
2. `require_once configuration/configure.php` (user rules — can reference extension classes)

So extension classes are available by the time `configure.php` runs.

## Files to Change

- `source/source/extension/loader.php` — create (new file, no-op PHP entry point)
- `source/source/loader.php` — add `require_once __DIR__ . '/extension/loader.php';` at the end

## CI Checks

- `source/`: `cd source && composer lint && composer test` (CI jobs: `checks`, `unit_test`)

## Notes

- No unit tests are required — this is a structural addition with no logic to test.
- The extension loader file must be a valid PHP file (starts with `<?php`) to avoid parse errors when required.
- Do not add this to the dev API's `loader.php` — the extension point is for the Tent proxy only.
