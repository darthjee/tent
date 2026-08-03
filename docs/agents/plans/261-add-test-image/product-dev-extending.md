# Product-Dev-Extending Plan: Add test image

Main plan: [plan.md](plan.md)

## Shared contracts

You document exactly the contract `infra` builds (see [plan.md](plan.md)'s "Shared contracts" section) — do not invent mount paths, image names, or defaults; use precisely: image `darthjee/tent-test`, mounts `./extension/`→`/var/www/html/extension/` and `./extension_tests/`→`/var/www/html/tests/extension/`, default `CMD` runs `vendor/bin/phpunit`, and the canonical `docker run` snippet from `plan.md`. If `infra`'s actual implementation ends up differing from the contract (e.g. a different default command override syntax), verify against the real Dockerfile/image before writing the docs — per your own convention of proving contracts against real code, not just asserting them.

## Implementation Steps

### Step 1 — Add a "Testing your extension" subsection to `HOW_TO_USE_DARTHJEE-TENT.md`

Location: new `### Testing your extension` subsection inside the existing `## Extending Tent` section (`docs/HOW_TO_USE_DARTHJEE-TENT.md`), placed after the existing `### Using custom classes in configuration` subsection (currently ending around line 790) and before the `## Reference` section (currently starting around line 794).

Content to cover:

- Why: the production `darthjee/tent` image has no PHPUnit/dev tooling, so extension code needs a separate image to test against.
- The `darthjee/tent-test` image, and that it bundles Tent's own source, full dev tooling (phpunit, pcov, phpcs, phpmd, phpdocumentor), and reusable test-support helpers.
- The two mounts, using the same `./extension/` directory already established earlier in this section (so the doc reads as "the same code you mount into production, plus a second mount for your tests") — do not introduce a different example directory name than what's used earlier in the section.
- The canonical `docker run` example from `plan.md`'s shared contract, adapted to match this doc's existing `docker-compose.yml`-flavored style if that reads better here (check how the rest of the file presents examples — mostly `docker-compose.yml` snippets — and decide whether a `docker run` one-liner or an equivalent compose service fits the surrounding style better; a `tent_tests`-style one-off compose service, consistent with how this repo's own `docker-compose.yml` runs its tests, may fit this doc better than a bare `docker run`).
- A one-line note that the default `phpunit.xml` baked into the image auto-loads `extension/loader.php` before running, and that the command is overridable for other workflows (e.g. `phpcs`).

### Step 2 — Update the table of contents

Add a `Testing your extension` entry to the `## Table of Contents` list (around line 9-36), nested under the existing `Extending Tent` entry, matching the existing indentation/anchor-link style used for other subsections in that list.

### Step 3 — Verify the documented example actually works

Per your standard verification convention: once `infra` has built the `dockerfiles/tent-test/Dockerfile`, build the image locally, write a small throwaway custom matcher/middleware + a matching PHPUnit test exactly as your new doc example shows, mount them in following your own instructions, and confirm the suite runs and passes. Fix the doc (not the Dockerfile) if the documented mounts/command don't match reality — flag it to `infra` instead if the image itself is the problem.

## Files to Change

- `docs/HOW_TO_USE_DARTHJEE-TENT.md` — add the "Testing your extension" subsection under "Extending Tent", and its Table of Contents entry.

## Notes

- Depends on `infra`'s Dockerfile/image work landing first (or at least being stable) before the "verify it actually works" step can run for real — coordinate ordering if these are implemented in parallel.
