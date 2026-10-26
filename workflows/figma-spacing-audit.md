# Workflow: figma-spacing-audit

Source skill: Figma Community/AI skill 70410 "spacing-audit" (Tran Mau Tri Tam).

## Loop

**What:** Audit spacing values against the 4/8pt scale; report every off-grid value as before→after; snap when implementing. **Trigger:** event — any PR touching layout/spacing in `autodrive-frontend`; or schedule — before each design handoff. **Checkpoint:** push right — summary list before any change; user approves snap (implementation is manual/next step).

## Audit spec (rubric)

### Scale
Base unit 8; 4 only as half-step for component-internal fine spacing.
Allowed: `0, 4, 8, 12, 16, 24, 32, 40, 48, 56, 64, 80, 96` (Tailwind: `0,1,3,4,6,8,10,12,14,16,20,24`).
- Structural (sections, card padding, layout margins): 8/16/24/32/48/64.
- Fine (icon-to-label, chip padding, tight stacks): 4/12/20.
- Any value not a multiple of 4 (Tailwind: non-integer, or `.5` steps other than via allowed set) = off-grid flag.
- Multiple of 4 that isn't multiple of 8 at layout level (e.g. gap 20/28) = "consider 8pt step" note, not a hard error.

### Project overlay (AGENTS.md brief)
Project grid = 4px; NEVER use `0.5 / 1.5 / 2.5 / 3.5` Tailwind steps — hard flags. `gap-x`/`py-*`/`px-*`/`space-*`/`gap-*` off-grid values all count.

### What to check (codebase lens instead of Figma canvas)
For each component/section: padding (`p/px/py/pt/pb/pl/pr`), gaps (`gap`, `space-x/y`), margins-of-content rhythm, inter-section rhythm. Inspect shared surfaces first (`components/ui`, `components/layout`, `components/filter`), then feature layouts.

### How to audit
1. Walk top-down. Compare every spacing value with the allowed scale.
2. Off-grid → choose nearest allowed step; when between two steps, round toward the group's surrounding rhythm (consistency within a group > per-element correctness).
3. Preserve exceptions, do not flag: 1px borders/hairlines (`border`, `ring-1` for structure), optical-alignment offsets, values driven by tokens/variables, container-query-driven responsive padding.

### Rules
- Report-only run: never change sizing behavior, structure, content, or styling.
- If spacing already comes from a design token/CVA variant, note it, don't override.
- Fine gaps round to 4 or 8, not larger steps.
- A whole group uniformly off-grid on purpose → flag once, ask before mass change.

### Output (exact)
1. **Summary first, before any change:** total elements sampled (files count), before/after list grouped by file in the form `Button.tsx padding: py-3.5 -> py-4`, plus untouched-with-reason (token/hairline/optical).
2. End with a one-line rhythm note: dominant step now / biggest off-grid cluster (e.g. "layout on 8pt rhythm; fine spacing on 4pt; 17 flagged values, 11 in DataTable-area paddings").
3. Optional verification: `pnpm run check:design-system` — quote its spacing findings if any.

### Do not
- Do not resize elements or reflow content.
- Do not invent a new scale.
- Do not touch radius/stroke/type unless asked.

## Scope

`autodrive-frontend`. Priority: `src/components/ui/*` (shared kit = highest leverage), `src/components/layout/*`, then top features (dashboard, students, payments). Sample broadly across features; state files inspected count.
