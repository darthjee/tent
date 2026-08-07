# product-dev-extending Plan: Allow the prepending of middlewares

Main plan: [plan.md](plan.md)

## Shared contracts

Relies on `tent`'s implementation of the `prependMiddlewares` rule key (see [plan.md](plan.md)'s "Shared contracts" for the exact shape and example). Document it as a sibling to `middlewares`, not a replacement — both keys coexist on the same rule.

## Implementation Steps

### Step 1 — `docs/guides/tent/defining-rules.md`

Add a `prependMiddlewares` bullet next to the existing `middlewares` one (line 7):

```markdown
- **`middlewares`** (optional) — transformations applied before or after the handler.
- **`prependMiddlewares`** (optional) — same shape as `middlewares`, but its entries run *before* the handler's built-in default middlewares (if any) instead of after.
```

### Step 2 — `docs/guides/tent/request-handlers.md`

Update the "middlewares are applied in order" section (around lines 269-277), which currently states:

> 1. Internal default middlewares are created first (inside the handler constructor)
> 2. Rule-level middlewares are appended afterward
>
> So rule-level middlewares run **after** the built-in default middlewares.

Add a third point covering `prependMiddlewares`, e.g.:

> `prependMiddlewares` entries are inserted *before* the internal default middlewares (step 1), while `middlewares` entries are still appended after them (step 2) — so the full order is: `prependMiddlewares` → internal defaults → `middlewares`.

### Step 3 — `docs/request-handlers.md`

This older/longer guide duplicates the same "rule-level middlewares run after the built-in default middlewares" explanation (lines 269-277, mirroring Step 2's target almost verbatim). Apply the same addition here for consistency, since both docs currently describe the same behavior independently.

### Step 4 — `docs/creating-middlewares.md`

Skim the "Middleware Configuration" and "Best Practices" sections (which currently only discuss ordering within a single `middlewares` list, e.g. "place middlewares that can short-circuit... before middlewares that modify the request"). Add a short note that `prependMiddlewares` is available when a middleware needs to run ahead of a handler's own defaults (e.g. `default_proxy`'s header rewriting), cross-referencing `defining-rules.md`.

### Step 5 — Verify extensibility end-to-end

Per this agent's usual charge, confirm a developer using the proxy can actually use `prependMiddlewares` as documented — write (or mentally trace through) a small example rule combining `prependMiddlewares` and `middlewares` on a `default_proxy` handler and confirm the documented order matches `tent`'s implementation (once merged). Flag any mismatch back rather than silently adjusting the docs to fit.

## Files to Change

- `docs/guides/tent/defining-rules.md` — add `prependMiddlewares` bullet.
- `docs/guides/tent/request-handlers.md` — extend the ordering explanation.
- `docs/request-handlers.md` — extend the same ordering explanation (duplicate doc).
- `docs/creating-middlewares.md` — add a short cross-referencing note.

## CI Checks

None — no CI job in `.circleci/config.yml` lints or tests `docs/`.

## Notes

- `docs/request-handlers.md` and `docs/guides/tent/request-handlers.md` currently duplicate this same explanation independently (287 vs. 100 lines, not otherwise identical) — this plan updates both to stay consistent with each other, but does not attempt to de-duplicate them; that's a pre-existing docs-organization concern outside this issue's scope.
