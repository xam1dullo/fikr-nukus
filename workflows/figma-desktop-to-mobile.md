# Workflow: figma-desktop-to-mobile

Source skill: Figma Community/AI skill 72495 "desktop-to-mobile" (atitude.design). Adapts desktop UI to a 390px mobile viewport — never "shrinks".

## Loop

**What:** Audit responsive behavior of key screens at 390px (and 320px): refit (stacking, nav transforms, type scaling, touch targets, content priority) rather than scale-down. **Trigger:** event — any PR touching responsive layouts; or schedule — before mobile QA. **Checkpoint:** findings list → user picks refit tickets. No implementation in the audit run.

## Audit spec (rubric)

### Lens rules (390px)
- **Refit, don't shrink:** tables/side-by-side → stack into cards; content priority: primary action & key data first, secondary collapsed.
- **Nav transform:** desktop sidebar/header nav → appropriate mobile pattern (drawer/bottom bar/burger) — hiding nav silently is a finding.
- **Type scaling:** headings shrink proportionally, never illegibly small; body stays ≥14px; inputs ≥16px (iOS zoom rule).
- **Touch targets:** all controls ≥ 44×44 (`min-h-11` project law); adjacent targets have ≥ 8px spacing.
- **No horizontal scroll:** anything at 390/320px scrolls horizontally = blocking finding.
- **Fixed widths on text** = finding (rows must wrap).
- **Critical actions never clipped** by overflow containers or hidden behind awkward patterns ("resize-and-look" test result documented).
- **Density:** desktop-dense dashboards reflow into vertical rhythm; tables with 6+ columns must have a defined mobile strategy (cards/column picker/horizontal scroll with affordance).

### Codebase method (no device lab)
- Identify layout strategies from code: breakpoints (`sm:/md:/lg:` usage, grid/flex patterns, `hidden`/`md:block` alternations, overflow containers, DataTable patterns, fixed `w-[Npx]`).
- Read each key screen's layout tree + estimate mobile behavior (what collapses, what stacks, what scrolls) — are the needed breakpoint classes actually present?
- `min-w-*`/`w-*` on text containers; `md:grid-cols-N` fallback below `md:`; `@container` usage (Tailwind v4 supports container queries — note absence where breakdown structure demands it).
- Note viewport meta, safe-area handling if PWA (`manifest`, `viewport-fit=cover`), iOS input zoom (input font-size < 16px).
- If a dev server/browser check is possible for the auditor — `npm run dev` + 390px viewport; else static analysis with "verify in browser" caveat list, never silently assumed.

### Data gather (before writing)
- Data of key grids: what grids/tables are shown at desktop, what mapping needed at mobile; which minor/hidden columns can collapse — basis for prioritized refit list.
- Standalone audit: quote the desktop→390px refit plan per screen (even though implementing is a later task).

### Output (exact)
1. **Screens audited** table — screen | layout strategy | 390px behavior (from code) | verdict (fits / suspect / breaks).
2. **Findings** — per screen, severity ranked: horizontal scroll, hidden nav, <44px targets, fixed-width text, clipped actions, illegible type.
3. **Refit plan per broken screen** — concrete: what stacks into what, nav transform proposal, content priority ordering, exact breakpoint classes to add/change.
4. **Caveat list** — anything needing browser verification.
5. **Verdict:** `Ready` / `Needs refit` / `Breaks at 390px`.

## Scope

`autodrive-frontend`: layout shell (`src/components/layout/*`), dashboard, students list/detail, payments — the highest-traffic screens. Representative sample ≥ 6 screens; boundaries stated.
