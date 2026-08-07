# Issue: Move docs/HOW_TO_USE_DARTHJEE-TENT.md to docs/guides

## Description
Move `docs/HOW_TO_USE_DARTHJEE-TENT.md` and the files it depends on into `docs/guides/`, so any developer can copy that folder wholesale into their own project to instruct their AI coding assistants, without needing anything else from this repo.

## Problem
The guide currently lives at `docs/HOW_TO_USE_DARTHJEE-TENT.md`, with its supporting pages in `docs/tent/*.md`, mixed alongside unrelated general developer docs directly under `docs/`. This makes it awkward to copy the guide into another project: there is no single dedicated folder to copy, the entry file uses SCREAMING_SNAKE_CASE naming inconsistent with its own kebab-case sibling files, and one supporting file it links to (`docs/creating-request-hashers.md`) lives outside the guide's own folder, which would break that link if only the guide folder were copied elsewhere.

## Expected Behavior
After the move, `docs/guides/` is a fully self-contained, portable folder: `docs/guides/how-to-use-tent.md` plus `docs/guides/tent/*.md` (12 existing files, renamed from `docs/tent/`) plus `docs/guides/tent/creating-request-hashers.md`. Every link inside that folder is relative and resolves correctly regardless of where the folder is copied to, with no dependency on any file outside `docs/guides/`.

## Solution
**Move (`git mv`):**
- `docs/HOW_TO_USE_DARTHJEE-TENT.md` -> `docs/guides/how-to-use-tent.md` (renamed to kebab-case to match its sibling folder)
- `docs/tent/*.md` (all 12 files) -> `docs/guides/tent/*.md` (the `docs/tent/` folder disappears)
- `docs/creating-request-hashers.md` -> `docs/guides/tent/creating-request-hashers.md` (the guide's one cross-link out of its own folder, folded in for full portability)

**Stay put (general developer docs, unrelated to the copyable guide):**
- `docs/README.md`
- `docs/request-handlers.md` (distinct document from `docs/tent/request-handlers.md`/its new location -- different content, different audience, confirmed not to merge)
- `docs/creating-middlewares.md`
- `docs/file-cache-middleware-matchers.md`
- `docs/adding-request-matchers.md`
- `docs/agents/**`

**Backward compatibility:** clean break, no redirect stub left at the old paths. Update every reference to point at the new `docs/guides/...` locations:
- `README.md` (top-level link to the guide)
- `DOCKERHUB_DESCRIPTION.md` (full GitHub blob URL, visible to external users on Docker Hub)
- `docs/README.md` (index table: both the "How to Use darthjee/tent" row and the "Creating Request Hashers" row -- the latter keeps its row but repoints to `docs/guides/tent/creating-request-hashers.md`)
- `.claude/agents/product-dev-extending.md` (path reference)
- `scripts/bump_version.sh` (`HOW_TO_USE` variable -- hardcodes the old path and `sed`s the guide's "Minimum version" line on every release; not a doc link, but must be updated or the next release silently stops updating that line)
- `docs/creating-middlewares.md` (line ~293, links to `creating-request-hashers.md` -- update the relative link to `guides/tent/creating-request-hashers.md` since that file is staying put while its target moves)

**Testing strategy:** no markdown-link-check tooling or docs CI exists in this repo, and this is a one-time reorg, so no new tooling is introduced. Verify manually as part of the PR:
- `grep -rn 'HOW_TO_USE_DARTHJEE-TENT\|docs/tent/' .` returns zero matches.
- Manually click/read through the moved guide's links once (`docs/guides/how-to-use-tent.md` -> each `docs/guides/tent/*.md` page -> its "<- Back" link) to confirm they resolve.

## Benefits
- Any developer can copy `docs/guides/` directly into their own project to instruct their AI coding assistants, with no broken links and no leftover dependency on this repo's structure.
- Naming consistency: the guide entry file and its supporting folder both use kebab-case.
- The general `docs/` index stays focused on repo-specific developer docs, cleanly separated from the portable guide.
