# Infra Plan: Add test image

Main plan: [plan.md](plan.md)

## Shared contracts

You produce the contract described in [plan.md](plan.md)'s "Shared contracts" section — image name, mounts, default `phpunit.xml`, default `CMD`, and bundled tooling/helpers. `product-dev-extending` documents exactly what you build here, so keep the final Dockerfile behavior matching that contract precisely (mount paths, bootstrap order, testsuite path, default command).

## Implementation Steps

### Step 1 — Create `dockerfiles/tent-test/Dockerfile`

New file, `FROM darthjee/dev_tent-base:<pinned version>` (pin the same way `dockerfiles/dev_tent/Dockerfile` pins `darthjee/dev_tent-base:0.0.2`).

- `COPY --chown=app:app` Tent's `composer.*` and `source/lib/` from the `source` build context, then `RUN composer install` (no `--no-dev`, so the full `require-dev` set — phpunit, pcov is already in the base, phpcs, phpmd, phpdocumentor — is installed) followed by `composer dump-autoload`.
- `COPY --chown=app:app` `source/tests/support/` into the image at `tests/support/` (relative to `/home/app/app`, i.e. reachable at `/var/www/html/tests/support/` via the existing `dev_tent-base` symlink from `/var/www/html` to `/home/app/app/source`) — reuse as-is, no changes to these files.

  Note: `tests/support/` currently lives under `source/tests/` (sibling to `source/source/`, not under it), while the image's web root symlink only covers `/home/app/app/source` → `/var/www/html`. Decide during implementation whether to `COPY` these support classes to a path under `/home/app/app/source/tests/support/` (so they land under the existing symlink) or to a separate `/home/app/app/tests/support/` path outside it, and use whichever matches how the default `phpunit.xml`'s bootstrap/autoload references them. Prefer keeping the path relative to the app root consistent with how Tent's own `phpunit.xml` (`source/phpunit.xml`) already references `tests/` today, to minimize surprises for anyone comparing the two.

- Add a default `phpunit.xml` (new file under this Dockerfile's build context, `COPY`'d into the image) with:
  - `bootstrap="vendor/autoload.php"` — then a second bootstrap step (or a small bootstrap PHP file this Dockerfile also adds) that conditionally `require_once`s `/var/www/html/extension/loader.php` only if that path exists, since the file won't exist unless a user actually mounts their extension.
  - `<testsuite>` `<directory>` pointing at `/var/www/html/tests/extension`.
- `CMD ["vendor/bin/phpunit"]`.

### Step 2 — Wire the new image into `scripts/build_docker_image.sh`

Add a `tent-test` case to `image_config()`:

```bash
tent-test)
  DOCKERFILE="dockerfiles/tent-test/Dockerfile"
  CONTEXT="source"
  IMAGE_NAME="darthjee/tent-test"
  ;;
```

Update the `show_help` usage line's `Images:` list to include `tent-test`.

### Step 3 — Add `Makefile` targets

Mirror the existing `ci-build-tent` / `ci-release-tent` targets (which already use `VERSION`, not `BASE_VERSION`, matching the "versioned in lockstep with `tent`" decision):

```makefile
ci-build-tent-test:
	./scripts/build_docker_image.sh build tent-test $(ARCH) $(VERSION)

ci-release-tent-test:
	./scripts/build_docker_image.sh release tent-test $(ARCH) $(VERSION)
```

Add both to the `.PHONY` line at the top of the file.

### Step 4 — Wire CI jobs in `.circleci/config.yml`

Add `build-and-release-tent-test-linux` and `build-and-release-tent-test-macos` jobs, copied from `build-and-release-linux`/`build-and-release-macos` (the `tent` ones) but calling `make ci-build-tent-test` / `make ci-release-tent-test` instead. Same `requires`, same branch/tag filters (`master` only, all tags).

Add both new jobs to the `workflows.test-and-release.jobs` list, and add them to `update-description`'s `requires` list is NOT needed (that job only pushes the Docker Hub description for `darthjee/tent`) — leave `update-description` untouched unless a separate description page for `tent-test` is wanted (out of scope here; not mentioned in the issue).

## Files to Change

- `dockerfiles/tent-test/Dockerfile` — new; builds the `tent-test` image.
- `dockerfiles/tent-test/phpunit.xml` (or similar path within this Dockerfile's build context) — new; default PHPUnit config baked into the image.
- `scripts/build_docker_image.sh` — add `tent-test` case to `image_config()` and update usage text.
- `Makefile` — add `ci-build-tent-test` / `ci-release-tent-test` targets and `.PHONY` entries.
- `.circleci/config.yml` — add `build-and-release-tent-test-linux` / `-macos` jobs and wire them into the `test-and-release` workflow.

## Notes

- Running the image with no mounts at all (e.g. a bare smoke-test `docker run --rm darthjee/tent-test`) will make PHPUnit fail because `/var/www/html/tests/extension` won't exist — that's expected/by design, not a bug to fix. Verify locally instead with mounted `extension/`/`extension_tests/` fixture directories, or by pointing `vendor/bin/phpunit` at a path known to exist (e.g. override the command to run Tent's own bundled test suite as a sanity check that the image itself is healthy).
- No `--no-dev` trimming per the issue's decision — the image intentionally bundles phpcs/phpmd/phpdocumentor alongside phpunit, even though only phpunit is exercised by the default `CMD`.
- Verify locally before pushing: `docker build -f dockerfiles/tent-test/Dockerfile source -t darthjee/tent-test:local`, then run it against a small throwaway `extension/`+`extension_tests/` fixture to confirm the default `phpunit.xml` bootstrap/testsuite wiring actually works end-to-end.
