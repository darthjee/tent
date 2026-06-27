# Infra Plan: Add Extension Framework

Main plan: [plan.md](plan.md)

## Shared contracts

The extension entry point in the container is `/var/www/html/extension/loader.php`. The `tent` agent creates the default no-op `source/source/extension/loader.php`. This agent documents the mechanism in `docs/HOW_TO_USE_DARTHJEE-TENT.md` and updates `scripts/bump_version.sh`.

The minimum version line format to add and maintain in `docs/HOW_TO_USE_DARTHJEE-TENT.md`:

```
**Minimum version:** [X.Y.Z](https://github.com/darthjee/tent/releases/tag/X.Y.Z)
```

Initial value: `**Minimum version:** [0.7.9](https://github.com/darthjee/tent/releases/tag/0.7.9)` — set to the next release, which will include this extension framework.

## Implementation Steps

### Step 1 — Add minimum version line to `docs/HOW_TO_USE_DARTHJEE-TENT.md`

Insert a minimum version line at the very top of the file, immediately after the `# How to Use darthjee/tent` heading and the introductory paragraph, but **before** the `---` separator and the `## Table of Contents` section. The file currently starts:

```markdown
# How to Use darthjee/tent

[Tent](https://github.com/darthjee/tent) is a PHP-based reverse proxy...

---

## Table of Contents
```

After the change it should look like:

```markdown
# How to Use darthjee/tent

**Minimum version:** [0.7.9](https://github.com/darthjee/tent/releases/tag/0.7.9)

[Tent](https://github.com/darthjee/tent) is a PHP-based reverse proxy...

---

## Table of Contents
```

### Step 2 — Document the extension mechanism in `docs/HOW_TO_USE_DARTHJEE-TENT.md`

Add a new section **"Extending Tent"** to both the Table of Contents and as a new top-level section before the `## Reference` section (near the end of the file).

#### Table of Contents addition

Add the following entry to the TOC, after the `Static Files` entry and before the `Complete Example Layout` entry:

```markdown
- [Extending Tent](#extending-tent)
```

#### New section content

Add this section before the `## Reference` section:

````markdown
## Extending Tent

Tent supports custom PHP classes (matchers, middlewares, handlers) via a mount-based extension mechanism — no fork or image rebuild required.

### How it works

Tent automatically includes `/var/www/html/extension/loader.php` after all core classes are loaded and before `configuration/configure.php` runs. By default this file is a no-op (an empty PHP file). To add custom classes, mount a `loader.php` file at that path:

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    volumes:
      - ./proxy/configuration/:/var/www/html/configuration/
      - ./proxy/extension/:/var/www/html/extension/
```

### Extension loader

Create `./proxy/extension/loader.php` with `require_once` calls for your custom classes:

```php
<?php

require_once __DIR__ . '/MyCustomMatcher.php';
require_once __DIR__ . '/MyCustomMiddleware.php';
```

Because the extension loader runs after all Tent core classes, your custom classes can extend any built-in class or implement any built-in interface.

### Using custom classes in configuration

Once loaded, your classes are available in `configure.php` by their fully-qualified name:

```php
<?php

use Tent\Configuration;

Configuration::buildRule([
    'handler' => ['type' => 'proxy', 'host' => 'http://backend:80'],
    'matchers' => [
        ['class' => 'MyCustomMatcher', 'pattern' => '/api/v2/']
    ],
    'middlewares' => [
        ['class' => 'MyCustomMiddleware']
    ]
]);
```
````

### Step 3 — Update `scripts/bump_version.sh` to also bump the minimum version

`bump_version.sh` currently bumps the version in `README.md`, `Makefile`, and `source/composer.json`. Add a `sed` command to also bump the minimum version line in `docs/HOW_TO_USE_DARTHJEE-TENT.md`.

The `HOW_TO_USE` variable should be set near the top of the script (alongside `README`, `MAKEFILE`, `COMPOSER_JSON`):

```bash
HOW_TO_USE="$ROOT_DIR/docs/HOW_TO_USE_DARTHJEE-TENT.md"
```

Then add a `sed` command after the existing `README` sed call:

```bash
sed -i '' \
  "s|\*\*Minimum version:\*\* \[.*\](https://github.com/darthjee/tent/releases/tag/.*)|**Minimum version:** [$VERSION](https://github.com/darthjee/tent/releases/tag/$VERSION)|" \
  "$HOW_TO_USE"
```

## Files to Change

- `docs/HOW_TO_USE_DARTHJEE-TENT.md` — add minimum version line near top; add "Extending Tent" section + TOC entry
- `scripts/bump_version.sh` — add `HOW_TO_USE` variable and `sed` call to bump the minimum version line

## Notes

- The minimum version is set to `0.7.9` because that is the next release and will be the first to ship the extension framework.
- The `sed` pattern uses `\*\*Minimum version:\*\*` to match the bold markdown markup safely.
- No CI checks apply to `scripts/` or `docs/` — no automated check is needed for this agent's changes.
