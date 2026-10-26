# Workflow: figma-find-animation-opportunities

Source skill: Figma Community/AI skill 68151 "find-animation-opportunities".

## Loop

**What:** Read-only scan for high-value, restrained motion opportunities in autodrive-frontend. Proposes — never implements. **Trigger:** event — before starting a polish/motion epic; or schedule — after major feature launches. **Checkpoint:** user decides which proposals become ticket scope. **Gate:** frequency + purpose + speed + function.

## Audit spec (rubric)

### Constrain the search to 4 really-visible interactions
- Enter (element arrives / content loads)
- Exit (element leaves / content is dismissed)
- Emphasis (user learns element changed / came into view)
- Hide (element disclosable / toggleable in place)

### Judgment filter — qualify only if a "yes, and" to most, and reasons below:
- **Frequency:** will users see it often? Never propose for once-ever moments.
- **Purpose:** does motion increase comprehension of what just happened?
- **Speed:** can it be done in ≤ 250ms without feeling abrupt?
- **Function:** does motion respond to a user gesture or state change?

### Restraint rules (hard)
- Chat/log-like streams (messages, audit log, notifications): no entrance animation, ever.
- Never animate the most important thing on screen (values, prices, grades).
- Table rows list: never animate initial or sequential appearance.
- Toasts that contain actions: no auto-dismiss or fade-out animation that fights interaction (delay alone must do the work).
- No decoration-only animation (bouncing CTAs, looping pulses) unless an explicit emotion/red-alert brief exists.

### Recipe domains (propose with exact recipes)
- Buttons/links transitions.
- Focus (in particular for overlays and dialogs).
- Drag-and-drop sorting.
- Hiding/showing in place (accordion, collapse, filter panels).
- Toasts / banners entering.
- Focus traps & modals (enter/exit, `overscroll-behavior: contain`, background inert).
- Skeletons → content swap (state transition, not shimmer decoration).
- Number/stat changes (`tabular-nums` + transform emphasis).
- Route/page changes (data fetching → content replace).
- View transitions if Vite/RSPA route structure permits (React Router view transitions API).

### Tech reality (project)
- No framer-motion in deps currently (check `package.json`); React 19 + Tailwind v4 — prefer CSS transitions/keyframes + `prefers-reduced-motion` guard; mention if a lib is genuinely warranted for a recipe (state tradeoff).
- Project brief: chips/controls `rounded-md`; overlays keep own depth rules.

### Output (exact, read-only)
1. **Opportunities** — grouped by execution cost ranked smallest-fix-first; for each:
   - Location: component + `file:line` + current behavior (what is there now).
   - Recipe: exact CSS/motion spec (properties, duration ms, easing, distance/opacity values, reduced-motion fallback).
   - Cost: S/M/L (CSS-only vs component change vs new dependency).
   - Gate verdict: frequency/purpose/speed/function one-liner each.
   - Restraint check: which hard rules it respects (cite it's not chat-stream/high-frequency/value-animated).
2. **Rejected candidates** — elements considered then disqualified BY A GATE with the reason (this section is mandatory).
3. **No implementation.** Proposals only. If allowed later, the proposal doubles as the spec.

## Scope

`autodrive-frontend`: interactive shared kit first (`components/ui` — dialog, dropdown-menu, collapsible, toast wherever defined, button), then layout (sidebar/nav/collapse), then dashboard/users tables (reject-heavy zone — perfect to demonstrate restraint). Confirm or deny existing motion conventions before proposing.
