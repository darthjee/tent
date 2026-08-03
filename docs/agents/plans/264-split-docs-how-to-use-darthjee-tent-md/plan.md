# Plan: Split docs/HOW_TO_USE_DARTHJEE-TENT.md

Issue: [264-split-docs-how-to-use-darthjee-tent-md.md](../issues/264-split-docs-how-to-use-darthjee-tent-md.md)

## Overview

`docs/HOW_TO_USE_DARTHJEE-TENT.md` (871 lines) becomes a short navigation hub, with its 12 top-level sections moved verbatim into one file each under a new `docs/tent/` directory. All links between the hub and `docs/tent/` are relative (`./tent/<file>.md` from the hub, `../HOW_TO_USE_DARTHJEE-TENT.md` back from each split file), since both are copied into consumer projects at unknown paths. This is a purely mechanical move — no content is rewritten, trimmed, or restructured — plus the follow-on doc/CI wiring needed for the moved files to work as standalone guides.

## Context

The file is the integration guide developers copy into their own projects for their coding agents to consume. Its single-file size makes it unwieldy, especially once copied out. Full rationale and the decisions behind the split (hub content, splitting strategy, overlap with existing internal docs, scope, and other references) are recorded in the issue file linked above — this plan implements those decisions as-is.

Existing internal docs (`docs/request-handlers.md`, `docs/creating-middlewares.md`, `docs/file-cache-middleware-matchers.md`) stay untouched: they're contributor-facing and not exported, so they are explicitly out of scope for this split (per the issue's "Overlap with existing internal docs" decision).

## Implementation Steps

### Step 1 — Create `docs/tent/` and split the content

Create `docs/tent/` and, for each top-level (`##`) section of the current `docs/HOW_TO_USE_DARTHJEE-TENT.md`, create one file holding that section's content verbatim (heading level shifted up by one, e.g. `## Request Handlers` → `# Request Handlers`, with its nested `###`/`####` headings shifted up to `##`/`###` accordingly). No wording changes. Source line ranges in the current file (before any edits) and destination files:

| Section (current `##` heading) | Lines | New file |
|---|---|---|
| Quick Start with Docker | 40–63 | `docs/tent/quick-start.md` |
| Configuration Folder Layout | 65–92 | `docs/tent/configuration-folder-layout.md` |
| Defining Rules | 94–112 | `docs/tent/defining-rules.md` |
| Request Handlers (incl. `default_proxy`, `proxy`, `static`, "Which handler should I use?") | 115–213 | `docs/tent/request-handlers.md` |
| Host Header and Why It Matters | 216–274 | `docs/tent/host-header.md` |
| Middlewares (incl. `FileCacheMiddleware`, `CacheCleanupMiddleware`, `SetHeadersMiddleware`, `RenameHeaderMiddleware`, `SetPathMiddleware`, `RedirectMiddleware`) | 277–434 | `docs/tent/middlewares.md` |
| Cache Configuration | 436–549 | `docs/tent/cache-configuration.md` |
| Frontend Dev Mode Flip | 552–630 | `docs/tent/frontend-dev-mode.md` |
| Static Files | 633–647 | `docs/tent/static-files.md` |
| Complete Example Layout | 650–740 | `docs/tent/complete-example.md` |
| Extending Tent (incl. "Testing your extension") | 743–825 | `docs/tent/extending-tent.md` |
| Reference | 828–871 | `docs/tent/reference.md` |

Each split file gets one added line not present in the source: a trailing `[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)` link, so a reader who lands on a split file directly can navigate back to the hub. Drop the `---` horizontal-rule separators that used to sit between sections in the monolithic file (they become file boundaries instead).

### Step 2 — Rewrite `docs/HOW_TO_USE_DARTHJEE-TENT.md` as the hub

Keep, unchanged: the `# How to Use darthjee/tent` title, the `**Minimum version:**` line (line 3 — `scripts/bump_version.sh` rewrites it in place by exact pattern match, so its text/format must not change), and the intro paragraph.

Replace the "Table of Contents" section with a flat list where each entry:
- Links to its `docs/tent/<file>.md` (relative path `./tent/<file>.md`), not to an in-page anchor.
- Is followed by a 1–2 sentence summary of that section, written fresh (not copied from the body, since the body no longer lives here) — style matches the existing guide table in `docs/README.md`.

Drop the nested sub-bullets the old TOC had for subsections (e.g. `default_proxy`, `FileCacheMiddleware`) — those are reachable by opening the relevant `docs/tent/*.md` file directly; the hub only needs one entry per split file.

Remove every section body that is now duplicated in `docs/tent/` (everything from the old `## Quick Start with Docker` heading through the end of the old `## Reference` section) — nothing but the hub summary list should remain below the intro.

### Step 3 — Verify

- Confirm every link in the hub's list resolves to a file created in Step 1 (12 links, 12 files).
- Confirm every split file's back-link resolves to `docs/HOW_TO_USE_DARTHJEE-TENT.md`.
- Diff each split file's body against the corresponding line range of the original (via `git show HEAD:docs/HOW_TO_USE_DARTHJEE-TENT.md` or similar) to confirm no wording changed beyond the heading-level shift.
- Confirm the `**Minimum version:**` line in the hub is byte-for-byte unchanged from before the split (`scripts/bump_version.sh` depends on its exact format).

## Files to Change

- `docs/HOW_TO_USE_DARTHJEE-TENT.md` — trimmed down to intro + `**Minimum version:**` line + linked/summarized TOC; all section bodies removed.
- `docs/tent/quick-start.md` — new, from "Quick Start with Docker".
- `docs/tent/configuration-folder-layout.md` — new, from "Configuration Folder Layout".
- `docs/tent/defining-rules.md` — new, from "Defining Rules".
- `docs/tent/request-handlers.md` — new, from "Request Handlers" (distinct from the existing internal `docs/request-handlers.md`, which is untouched).
- `docs/tent/host-header.md` — new, from "Host Header and Why It Matters".
- `docs/tent/middlewares.md` — new, from "Middlewares" (distinct from the existing internal `docs/creating-middlewares.md`, which is untouched).
- `docs/tent/cache-configuration.md` — new, from "Cache Configuration".
- `docs/tent/frontend-dev-mode.md` — new, from "Frontend Dev Mode Flip".
- `docs/tent/static-files.md` — new, from "Static Files".
- `docs/tent/complete-example.md` — new, from "Complete Example Layout".
- `docs/tent/extending-tent.md` — new, from "Extending Tent".
- `docs/tent/reference.md` — new, from "Reference".

No other file needs to change: `README.md`, `docs/README.md`, `DOCKERHUB_DESCRIPTION.md`, `.claude/agents/product-dev-extending.md`, and `scripts/bump_version.sh` all reference `docs/HOW_TO_USE_DARTHJEE-TENT.md` by path only, which does not change (confirmed in the issue's "Other references" section).

## Notes

- This is a docs-only change with no associated CI job (no markdown lint/link-check job exists in `.circleci/config.yml`), so no `## CI Checks` section applies.
- Keep the whole change to a single commit (or a small number of atomic commits) per `AGENTS.md`'s "Atomic commits" / "Small PRs" guidance — this is small and mechanical enough not to need sub-issues.
- No specialist agent split: every file touched is a root-level/docs file, squarely the architect's own scope per `AGENTS.md`'s agent list — none of `dev-api`, `frontend`, `infra`, `tent`, or `product-dev-extending` have code, config, or extension-verification work here.
