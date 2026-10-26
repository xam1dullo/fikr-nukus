# Workflow: figma-component-handoff

Source skill: Figma Community/AI skill 67998 "component-handoff" (component documentation for builds). Codebase equivalent: audit component **documentation readiness** + **variants/states/props structure**.

## Loop

**What:** For each component on the handoff surface: verify props/variants/states are structured, discoverable, and consistent so consumers (and future agents) can use them without reading source. **Trigger:** event — after adding/updating any shared component or before opening its code Component page/stories; or schedule — per epic. **Checkpoint:** push right; results feed a docs improvement ticket list, not instant rewrites.

## Audit spec (rubric)

### Per component, check 5 things
1. **specStructure** — interface is single source of truth: typed props, no spread-anywhere gray zone, variants expressed as a typed union or CVA, not prop soup.
2. **propsInventory** — every prop documented (`TSDoc`/JSDoc on the type + example default); usage discoverable; constraints said (length, participants, values).
3. **statesAndVariants** — all UI states represented and testable: default/hover/focus-visible/active/disabled/loading/error/empty + size/density/variant axes; missing states = finding.
4. **compositionGuidelines** — how to compose (slot/children API, compound components, `asChild` patterns), what NOT to do (nested cards, double borders).
5. **tokensConformance** — colors/radius/spacing/icon/typography from tokens per project brief; no one-off values that handoff can't express.

### Codebase specifics (project)
- Components live in `src/components/ui/*` (+ `src/shared/ui/*`). Stories exist for some (`*.stories.tsx`) — treat story coverage as the "component page" equivalent; `.test.tsx` coverage of states as the "spec" equivalent.
- Check consistency between props names across the kit (`data-*` attribute conventions, `size` unions elsewhere) — divergent vocabularies are findings.
- Per AGENTS.md brief: surfaces only Card/StatCard/DataCard, radius caps, Phosphor icons, `min-h-11` targets, i18n strings — conformance failures are component-level handoff findings.

### Testing requirements (from skill's structure, adapted)
- Ensure pain point coverage: at least one scenario per documented state axis where feasible (test exists that renders disabled/loading/empty variant).
- Components added/updated must have their `*.stories.tsx`/`.test.tsx` updated in same PR — missing = finding.
- For regenerated/edited variants: props interface diff'd against previous behavior — silent API changes flagged.

### Output (exact)
1. **Component inventory table** — component | has stories | has state tests | states covered (list) | variant axes | findings count.
2. **Findings** — per component, per the 5 checks, each with `file:line` evidence and the exact missing artifact (e.g. "`DataTable.tsx` — no disabled row state test; add case to `DataTable.test.tsx`").
3. **Top gaps ranked** — 3–5 items by consumer impact (shared kit > feature components).
4. **Verdict:** `Ready` / `Needs documentation work` / `Not handoff-ready`.

## Scope

`autodrive-frontend/src/components/ui/*` and `src/shared/ui/*` first; layout components second. Feature-local components only when they duplicate a shared kit's job (duplication = finding).
