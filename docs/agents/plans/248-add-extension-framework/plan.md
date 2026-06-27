# Plan: Add Extension Framework

Issue: [248-add-extension-framework.md](../issues/248-add-extension-framework.md)

## Overview

Add a lightweight extension point to Tent so users can inject custom PHP classes (matchers, middlewares, handlers) into the container via a Docker volume mount, without forking the image. The implementation is a nearly-empty `extension/loader.php` file that is `require_once`'d at the end of `source/source/loader.php` (after all core classes are loaded). The HOW_TO_USE guide is updated to document the extension mechanism, and `scripts/bump_version.sh` is updated to also track the minimum version in that document.

## Agents involved

- [tent](tent.md)
- [infra](infra.md)

## Shared contracts

### Extension loader path

The file created by the `tent` agent at `source/source/extension/loader.php` is the extension entry point that users mount into the container. The `infra` agent documents this path in `docs/HOW_TO_USE_DARTHJEE-TENT.md`:

- Container path: `/var/www/html/extension/loader.php`
- Source path (in repo, the no-op default): `source/source/extension/loader.php`

### Minimum version line format in `docs/HOW_TO_USE_DARTHJEE-TENT.md`

The `infra` agent adds a minimum version line at the very top of the file (before the table of contents). The `infra` agent also updates `scripts/bump_version.sh` to replace this line on each release. The format must be:

```
**Minimum version:** [X.Y.Z](https://github.com/darthjee/tent/releases/tag/X.Y.Z)
```

The initial value when this PR merges will be the next release (0.7.9), to be set by `scripts/bump_version.sh` when run.

### Branch

`issue-248`
