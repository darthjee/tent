# Issue: Add test image

## Description

Tent supports extending it with custom PHP classes (matchers, middlewares, handlers) through a mount-based extension mechanism: a user mounts their code at `/var/www/html/extension/`, and Tent auto-includes `/var/www/html/extension/loader.php` after core classes are loaded and before `configuration/configure.php` runs. This is already documented in `HOW_TO_USE_DARTHJEE-TENT.md`'s "Extending Tent" section.

However, there's no way for an extension author to actually run automated tests against their custom classes. The released `darthjee/tent` image is a lean production image with no dev dependencies and no PHPUnit. The other existing images aren't a fit either: `darthjee/dev_tent-base` is a generic base (composer dev deps + pcov) that doesn't bundle Tent's own source or test helpers, and `dev_tent` — which does bake in a project's composer install on top of that base — is never actually published to Docker Hub; it's only built locally via `docker-compose`'s `base_build` service for internal development.

## Problem

A user developing a Tent extension has no released image that bundles Tent's source, its dev/test tooling (PHPUnit, pcov, etc.), and a ready-to-use test harness wired to their own extension code — so there's no practical way to write and run automated tests for a custom matcher, middleware, or handler.

## Expected Behavior

A user should be able to pull `darthjee/tent-test`, mount their extension source and their extension's tests, and get PHPUnit results with zero extra configuration:

```bash
docker run --rm \
  -v ./extension:/var/www/html/extension \
  -v ./extension_tests:/var/www/html/tests/extension \
  darthjee/tent-test
```

- `./extension/` → `/var/www/html/extension/` — unchanged from the existing documented mechanism (custom classes + `loader.php`).
- `./extension_tests/` → `/var/www/html/tests/extension/` — new; the user's own PHPUnit test classes, kept as a separate mount from their source classes.

The default `CMD` runs `vendor/bin/phpunit`, so the command above runs the suite immediately. The command is overridable (e.g. `/bin/bash` for debugging, or other composer scripts like `lint`) for other workflows.

## Solution

Release a new image, **`darthjee/tent-test`**, purpose-built for testing extensions.

### Image contents

A new dedicated Dockerfile at `dockerfiles/tent-test/Dockerfile`, `FROM darthjee/dev_tent-base`, baking in:

- Tent's `source/lib` (core classes) and `vendor/` — installed from the same `composer.json` Tent itself uses, so the **full** `require-dev` set is included (phpunit, pcov, phpcs, phpmd, phpdocumentor), not a trimmed-down subset. No extra composer.json needed.
- `tests/support/` helper classes (`DummyRequestMiddleware`, `QuickResponseMiddleware`, `DummyResponseMiddleware`, `FileSystemUtils`, `RequestToBodyHandler`) as a reusable base for extension authors writing their own tests. These are being adopted as quasi-public API for this purpose — worth a doc note that they may evolve between Tent versions.
- A default `phpunit.xml`: bootstrap requires `vendor/autoload.php` then `/var/www/html/extension/loader.php`; testsuite points at `/var/www/html/tests/extension`.

### Versioning & release pipeline

- `tent-test` is versioned and released in lockstep with `darthjee/tent`'s own `VERSION` (not a separate version line like `dev_tent-base`'s `BASE_VERSION`), since it bakes in the same Tent source per release.
- CI wiring in `.circleci/config.yml` follows the same pattern as the existing `build-and-release-linux`/`build-and-release-macos` jobs for `tent` (new linux + macos build/release jobs for `tent-test`).
- New `image_config` case for `tent-test` in `scripts/build_docker_image.sh`, plus corresponding `Makefile` targets.

### Documentation

Update `HOW_TO_USE_DARTHJEE-TENT.md`'s "Extending Tent" section to document the new testing workflow: the two mounts, the default `phpunit.xml` behavior, and an example `docker run` invocation.

### Alternatives considered

Publishing the existing (currently unpublished) `dev_tent` image as-is was considered and rejected — it's a generic base without Tent's `tests/support/` helpers or a phpunit config wired to a conventional extension-test path, so it wouldn't give a zero-config testing experience out of the box.

### Backward compatibility

N/A — this is a net-new image. No changes to the existing `tent`, `dev_tent`, or `dev_tent-base` images or their behavior.

## Benefits

- Extension authors get a zero-config way to run automated tests against their custom matchers, middlewares, and handlers alongside Tent's own core classes.
- Reuses the same mount-based extension mechanism already documented for production, so there's nothing new to learn beyond the added test mount.
- Keeps the test image versioned in lockstep with `tent`, avoiding drift between what an extension is tested against and what it actually runs on in production.
