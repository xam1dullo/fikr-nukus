# Workflow: figma-superfuture-design-review

Source skill: Figma Community/AI skill 65434 "superfuture-design-review" (Joey Primiani; from crit.officialjp.com).

## Loop

**What:** Senior-level craft critique of autodrive-frontend screens — opinionated and specific, not a checklist dump. **Trigger:** event — any PR that touches `autodrive-frontend/src/**` UI (attach findings to the PR), or schedule — weekly sweep of the highest-traffic surfaces. **Checkpoint:** push right — run fully, then present a brief (counts + top findings + verdict) to the user; full report linked. **Brief:** decision-ready digest, never raw lists.

## Audit spec (rubric)

Evaluate against every dimension; check actual numbers, not vibes.

### Visual hierarchy & layout
- One clear focal point; obvious primary action. Scan path follows importance.
- Related items grouped (proximity); unrelated separated. Grid-aligned shared edges; no near-but-not-quite alignments.
- Whitespace as structure. Inter-group gap ≥ 2× intra-group gap.

### Typography
- Type scale ≈5–7 steps, each ≥ ~1.2× distinct. Line-height: display 1.0–1.2, body 1.4–1.6.
- Measure ~45–75ch body. Display tightened letter-spacing; labels slightly loose.
- ≤2 typefaces; consistent weights; tabular numerals where aligning (changes shift layout: `tabular-nums`).

### Spacing & rhythm
- Consistent 4/8px scale — no arbitrary 13px/27px gaps. Vertical rhythm consistent; padding symmetric where expected. Touch UIs roomier than dense dashboards.

### Color & contrast
- WCAG AA: body ≥ 4.5:1; large text/UI ≥ 3:1. Never color alone (icon/label too). Restrained palette; accent = `primary` only (project rule).

### Motion
- Purposeful, ~150–250ms UI / 300–500ms entrance; ease-out enters, ease-in exits; honors `prefers-reduced-motion`; no layout shift/jank.

### Component states
- Every interactive element: hover, focus-visible, active, disabled. Visible focus ring. Loading/empty/error designed. Tap targets ≥ 44×44 (`min-h-11` per project).

### Accessibility
- Semantic headings order; `button` vs `a`; landmarks; ARIA only to fill gaps; alt text; label↔input association; keyboard operability; zoom 200%.

### Responsiveness
- Fluid type/spacing or sensible breakpoints; no horizontal scroll; no overflow at 320–1440+.

### Content & copy
- CTA labels describe the action (not "Submit"); microcopy human; no misleading states.

### Consistency & tokens
- Tokens reused (color/type/spacing/radius); no one-off values; radius/lucide→Phosphor per project brief; surfaces Card/StatCard/DataCard only.

## Report format

Group by severity, lead with the few that matter:

- 🔴 **Blocking** — broken, inaccessible, fails WCAG AA, unusable on a target device.
- 🟠 **Important** — noticeably hurts hierarchy, readability, usability, consistency.
- 🟡 **Polish** — refinement.

Each finding: **What** (exact element/file:line) → **Why** (one line craft reason) → **Fix** (exact value, never "increase spacing"). One root cause per row; list all confirmed locations in it.

End with **Strengths** (2–4) + the single highest-leverage change first + **Verdict**: `Block` / `Needs changes` / `Approve`.

## Scope

`autodrive-frontend`: shared surfaces first (`src/components/ui`, `src/components/layout`, `src/components/filter`), then highest-traffic features (dashboard, students, payments). Extend to other features as time allows; state boundaries honestly — never imply uninspected surfaces were reviewed.
