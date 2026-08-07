# Plan: Move docs/HOW_TO_USE_DARTHJEE-TENT.md to docs/guides

Issue: [271-move-docs-how-to-use-darthjee-tent-md-to-docs-guides.md](../../issues/271-move-docs-how-to-use-darthjee-tent-md-to-docs-guides.md)

## Overview

Relocate the integration guide (`docs/HOW_TO_USE_DARTHJEE-TENT.md` + `docs/tent/*.md` + `docs/creating-request-hashers.md`) into a single self-contained `docs/guides/` folder, renaming the entry file to kebab-case (`docs/guides/how-to-use-tent.md`), so it can be copied wholesale into another project. This is a pure documentation/path reorg — no source code, no CI job covers it, and it stays a single cross-cutting change handled directly rather than split across specialist agents (the only non-docs touch, `scripts/bump_version.sh`, is a single path-variable fix inseparable from the rename itself).

## Context

Today the guide's entry file and its 12 supporting pages already form a self-contained relative-link cluster, with exactly one dependency outside it: `docs/creating-request-hashers.md`. That file must move into the guide folder too for true portability. The entry file's SCREAMING_SNAKE_CASE name is also being normalized to kebab-case to match its sibling folder. Every reference to the old paths repo-wide (docs, root files, agent config, and one release script) must be updated; this is a clean break with no redirect stub.

## Implementation Steps

### Step 1 — Move the files with `git mv`

```bash
mkdir -p docs/guides/tent
git mv docs/HOW_TO_USE_DARTHJEE-TENT.md docs/guides/how-to-use-tent.md
git mv docs/tent/*.md docs/guides/tent/
git mv docs/creating-request-hashers.md docs/guides/tent/creating-request-hashers.md
```

`docs/tent/` should end up empty and untracked (git does not track empty directories, so no further cleanup is needed).

### Step 2 — Fix links inside the moved folder

These are the only links whose *target* also moved, so they need content changes (not just relocation):

- `docs/guides/tent/cache-configuration.md`: `../creating-request-hashers.md` → `./creating-request-hashers.md` (now same directory).
- `docs/guides/tent/creating-request-hashers.md`: `tent/extending-tent.md` → `extending-tent.md` (now same directory).
- `docs/guides/how-to-use-tent.md`: `./creating-request-hashers.md` → `./tent/creating-request-hashers.md` (file now lives inside `tent/`).
- All 12 files under `docs/guides/tent/*.md`: their trailing back-link `[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)` → `[← Back to How to Use darthjee/tent](../how-to-use-tent.md)` (same relative depth, only the filename changed).

The rest of `docs/guides/how-to-use-tent.md`'s Table of Contents (`./tent/*.md` links) needs no change — the relative structure between the entry file and the `tent/` subfolder is unchanged by the move.

### Step 3 — Update cross-repo references to the old paths

- `README.md:19` — `docs/HOW_TO_USE_DARTHJEE-TENT.md` → `docs/guides/how-to-use-tent.md`
- `DOCKERHUB_DESCRIPTION.md:162` — full blob URL: `.../blob/main/docs/HOW_TO_USE_DARTHJEE-TENT.md` → `.../blob/main/docs/guides/how-to-use-tent.md`
- `docs/README.md:9` — `HOW_TO_USE_DARTHJEE-TENT.md` → `guides/how-to-use-tent.md`
- `docs/README.md:14` — `creating-request-hashers.md` → `guides/tent/creating-request-hashers.md` (row is kept, just repointed)
- `.claude/agents/product-dev-extending.md:14` — `docs/HOW_TO_USE_DARTHJEE-TENT.md` → `docs/guides/how-to-use-tent.md`
- `docs/creating-middlewares.md:293` — `creating-request-hashers.md` → `guides/tent/creating-request-hashers.md`

### Step 4 — Update `scripts/bump_version.sh`

Line 10:

```bash
HOW_TO_USE="$ROOT_DIR/docs/HOW_TO_USE_DARTHJEE-TENT.md"
```

becomes:

```bash
HOW_TO_USE="$ROOT_DIR/docs/guides/how-to-use-tent.md"
```

No other line in the script references the guide path (it only uses the `$HOW_TO_USE` variable downstream).

### Step 5 — Verify

- `grep -rn 'HOW_TO_USE_DARTHJEE-TENT\|docs/tent/' .` from the repo root must return zero matches.
- Manually read through `docs/guides/how-to-use-tent.md`'s Table of Contents, following each `./tent/*.md` link, the `./tent/creating-request-hashers.md` link, and each page's "← Back" link, to confirm they all resolve.

## Files to Change

- `docs/HOW_TO_USE_DARTHJEE-TENT.md` → `docs/guides/how-to-use-tent.md` (git mv + rename; internal `creating-request-hashers.md` link updated)
- `docs/tent/*.md` (12 files) → `docs/guides/tent/*.md` (git mv; back-links updated; `cache-configuration.md` and `creating-request-hashers.md` get an extra link fix each)
- `docs/creating-request-hashers.md` → `docs/guides/tent/creating-request-hashers.md` (git mv; internal `extending-tent.md` link updated)
- `README.md` — update guide link (line 19)
- `DOCKERHUB_DESCRIPTION.md` — update guide blob URL (line 162)
- `docs/README.md` — update two rows (lines 9, 14)
- `.claude/agents/product-dev-extending.md` — update path reference (line 14)
- `docs/creating-middlewares.md` — update `creating-request-hashers.md` link (line 293)
- `scripts/bump_version.sh` — update `HOW_TO_USE` variable (line 10)

## Notes

- Clean break: no redirect stub is left at any old path.
- No CI job in `.circleci/config.yml` covers markdown/docs changes, so no automated check needs updating; verification is manual (Step 5).
- `docs/request-handlers.md` is a distinct document from the moved `docs/tent/request-handlers.md` (different content/audience) and stays where it is — do not confuse the two during the move.
