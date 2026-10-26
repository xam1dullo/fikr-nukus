# Workflow: figma-create-anatomy

Source skill: Figma Community/AI skill 71321 "create-anatomy" (JRD-byte/uspec). Generates numbered anatomy annotations + attribute tables for a component. Codebase equivalent: generate **anatomy documentation** (structural breakdown with numbered parts + attribute tables) matching the internal implementation exactly — never described from memory.

## Loop

**What:** For handoff-worthy shared components, produce/refresh an anatomy doc: numbered regions, per-region attributes, and accurate composition tree — verified against code. **Trigger:** event — new/updated version of a shared component; before major refactors (anatomy keeps them honest); or documentation sprint. **Checkpoint:** docs saved; code-auxilles verified (attribute names, hoisted slots) — anatomy drift is itself a finding.

## Audit spec (rubric)

### Per component, produce (must match code exactly)
1. **Numbered anatomy** — decomposition of the rendered surface into numbered parts:
   - number, region name (`primary`, `meta`, `action`, `data window`, `sidebar`, `focus ring`…)
   - containing element + classes/tokens (`file:line` evidence)
   - layout role in composition tree (slot / flex item / absolutely positioned).
2. **Attribute tables** per numbered part — name, mapped prop/CSS var/token, readable definition, constraints/participants. Names must literally exist in implementation (grep-verified against the DOM/classes output or the prop interface).
3. **Composition tree** — parent → children slots with nesting depth, mirroring the actual JSX return (root to leaves), including conditional branches (e.g. `{error && …}`) as separate branches.
4. **Annotation callouts** — notable: a11y semantics (`role`, `aria-*`), keyboard behavior (`tabIndex`, handlers), portal/targets, safe areas; each with `file:line`.
5. **State matrix** — each visual state (default/hover/focus/active/disabled/loading/error/empty) × variant axis found in code, with the class/token the state is expressed by.

### Verification (the anti-drift core)
- Every attribute name in the table from the actual implementation! Grep-verified (layers or prop names checked). No invented attribute names.
- If an attribute maps to a named token — confirm domain and mention (e.g. `--spacing-*` vs Tailwind `p-4` class, real one wins).
- Ensure deferred identifiers (icon `currentColor`, `aria-labelledby` targets, i18n keys) included where component consumes them externally — anatomy documents dependencies too (strings from `useTranslation`, icons from Phosphor set).

### Schema requirements
- Anatomy sections numbered `1.` `2.` … consistent
- No duplication across tables; one source of truth per attribute; cross-references by number (e.g. "state matrix row 2 → part ② action").

### Output (exact)
For each component: **File header** (component, path, owner, updated date, source skill) → `## Anatomy` numbered decomposition + one fenced tree → `## Attributes` tables per part → `## State matrix` → `## Annotation callouts` → `## Dependencies` (tokens, i18n keys, icons, slots). Docs location: write to audit run dir (handoff later moves them beside source or to docs/, decided by user at checkpoint).

## Scope run-1 (docs sprint sample, 6 components in priority order)

1. `StatCard.tsx` (+ stories counterparts noted)
2. `DataCard.tsx`
3. `DataTable.tsx`
4. `EmptyState.tsx`
5. `EntityDetailShell.tsx`
6. `button.tsx` (+ `dialog.tsx` if budget allows)

## Scope guard

Shared kit only (`src/components/ui`, `src/shared/ui`) — feature components out of scope until a full docs run. If a component is a thin wrapper delegating to `src/components/ui/*` primitives, note inheritance explicitly within its anatomy panel and mark `Derived` instead of duplicating attributes fully.
