# Issue: Split docs/HOW_TO_USE_DARTHJEE-TENT.md

## Description

`docs/HOW_TO_USE_DARTHJEE-TENT.md` is the integration guide developers copy into their own projects so their coding agents know how to use Tent. It currently holds every topic in one file (~870 lines): Quick Start, Configuration Folder Layout, Defining Rules, Request Handlers, Host Header, Middlewares, Cache Configuration, Frontend Dev Mode Flip, Static Files, Complete Example Layout, Extending Tent, and Reference.

## Problem

The file is too big to be a comfortable single reference — especially since it's meant to be copied wholesale into other projects for a coding agent to consume there.

## Solution

Split the content out into `docs/tent/`, with `docs/HOW_TO_USE_DARTHJEE-TENT.md` becoming a navigation hub. Since both the hub and `docs/tent/` get copied into other projects at unknown paths, all links between them must be relative.

**Hub file content.** The hub keeps the intro paragraph and the `**Minimum version:**` line (updated in place by `scripts/bump_version.sh`), plus a Table of Contents where each entry links to its file under `docs/tent/` and is followed by a 1-2 sentence summary — similar in spirit to the guide table already in `docs/README.md`. No section body stays inline in the hub.

**Splitting strategy.** One `docs/tent/<slug>.md` file per top-level (`##`) section of the current doc — a strict 1:1 mapping onto the hub's Table of Contents, including the two very short sections ("Defining Rules", "Static Files"), which still get their own file rather than being folded into a neighbor. Filenames follow the kebab-case convention already used in `docs/` (e.g. `request-handlers.md`). Expected files:

- `docs/tent/quick-start.md`
- `docs/tent/configuration-folder-layout.md`
- `docs/tent/defining-rules.md`
- `docs/tent/request-handlers.md`
- `docs/tent/host-header.md`
- `docs/tent/middlewares.md`
- `docs/tent/cache-configuration.md`
- `docs/tent/frontend-dev-mode.md`
- `docs/tent/static-files.md`
- `docs/tent/complete-example.md`
- `docs/tent/extending-tent.md`
- `docs/tent/reference.md`

**Overlap with existing internal docs.** `docs/request-handlers.md`, `docs/creating-middlewares.md`, and `docs/file-cache-middleware-matchers.md` already exist and cover similar ground to some of the sections above, but they're internal contributor docs that stay in this repo, while `docs/tent/` is exported to other projects — so the two sets can't cross-link. Keep them fully separate: `docs/tent/request-handlers.md` and `docs/tent/middlewares.md` are self-contained, integrator-facing files distinct from their internal namesakes, duplicating content where necessary rather than merging or linking across.

**Scope.** Purely a mechanical split — content moves as-is from `HOW_TO_USE_DARTHJEE-TENT.md` into the new files, with only the minimal edits needed to make it work as separate files (headings, relative links, a hub summary line per section). No rewriting, trimming, or restructuring of the actual guidance.

**Other references.** `docs/HOW_TO_USE_DARTHJEE-TENT.md` keeps its path and continues to exist as the hub, so existing references to it (`README.md`, `docs/README.md`, `DOCKERHUB_DESCRIPTION.md`, `.claude/agents/product-dev-extending.md`, `scripts/bump_version.sh`) all remain valid and need no changes.
