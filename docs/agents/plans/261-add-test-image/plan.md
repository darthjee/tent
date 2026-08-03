# Plan: Add test image

Issue: [261-add-test-image.md](../../issues/261-add-test-image.md)

## Overview

Release a new `darthjee/tent-test` Docker image so extension authors can run PHPUnit against their custom matchers/middlewares/handlers with zero extra config. This is a two-part change: `infra` builds and releases the image (new Dockerfile, build-script wiring, CI jobs), and `product-dev-extending` documents the resulting testing workflow in the developer-facing extension guide.

## Agents involved

- [infra](infra.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

`infra` produces the following, and `product-dev-extending`'s documentation must describe exactly this (no more, no less):

- **Image name:** `darthjee/tent-test`, versioned in lockstep with `darthjee/tent`'s own `VERSION` (currently `0.9.1`) — same tag scheme, no separate `BASE_VERSION`-style line.
- **Mounts:**
  - `./extension/` → `/var/www/html/extension/` — identical to the existing production "Extending Tent" mechanism (custom classes + `loader.php`).
  - `./extension_tests/` → `/var/www/html/tests/extension/` — new; the user's own PHPUnit test classes.
- **Default `phpunit.xml` baked into the image:**
  - `bootstrap` requires `vendor/autoload.php` then `/var/www/html/extension/loader.php`.
  - `testsuite` points at `/var/www/html/tests/extension`.
- **Default `CMD`:** `vendor/bin/phpunit` — running the container with no extra args runs the suite immediately; the command is overridable (`/bin/bash`, `composer lint`, etc.).
- **Canonical invocation example** (this exact snippet is what the docs should show):
  ```bash
  docker run --rm \
    -v ./extension:/var/www/html/extension \
    -v ./extension_tests:/var/www/html/tests/extension \
    darthjee/tent-test
  ```
- **Bundled tooling:** the full `require-dev` set from `source/composer.json` (phpunit, pcov, phpcs, phpmd, phpdocumentor) — not a trimmed subset.
- **Bundled test helpers:** `source/tests/support/` classes (`DummyRequestMiddleware`, `QuickResponseMiddleware`, `DummyResponseMiddleware`, `FileSystemUtils`, `RequestToBodyHandler`) are available inside the image for reuse, at the same relative path they live at in the Tent repo (`tests/support/` under the app root).
