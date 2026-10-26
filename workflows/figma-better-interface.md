# Workflow: figma-better-interface

Source skill: Figma Community/AI skill 61238 "better-interface" (Jakub Krehel).

## Loop

**What:** One consolidated 5-domain review (accessibility ≥ layout ≥ writing ≥ typography ≥ UI polish) of the interface as one system. **Trigger:** event — any PR touching `autodrive-frontend/src/**` UI (quick mode: 5 findings, `HIGH/MEDIUM` only), or schedule — full mode review of shared surfaces. **Checkpoint:** push right — full report written, brief returned. **Brief:** one ranked findings table; never five disconnected domain reports.

## Audit spec (rubric)

Review in this order — foundational failures must not be hidden by polish:
1. Accessibility → 2. Layout → 3. Writing → 4. Typography → 5. UI polish.
When two domains cover one issue, assign it to the one that owns the rule; report once.

**Evidence rule:** every finding cites `path/file:line` + shows/pins the current implementation. Read-only by default.

### Accessibility
- Native elements first: `<button>` for actions, `<a href>` for nav (Cmd/Ctrl/middle-click must work); never `<div onClick>`; no ARIA where a native element exists.
- `:focus-visible` styled (≥ 2px solid perimeter); never `outline: none` without verified replacement.
- Full keyboard support (ARIA APG patterns): Escape closes overlays, arrows inside composite widgets; only `tabindex=0/-1`, never positive.
- Modals: focus trapped+restored, background `inert`, `overscroll-behavior: contain`.
- Hit areas: 24×24 minimum (WCAG 2.5.8 AA); aim 44×44 touch / 40×40 desktop — project enforces `min-h-11`.
- Labels: `<label for>` or wrapping; placeholder is never a label; `autocomplete`+`name`; never block paste.
- Icon-only buttons: descriptive `aria-label`; decorative `aria-hidden="true"` (never on focusable).
- Don't rely on color alone; measure contrast pairs; report pair + requirement missed.
- `prefers-reduced-motion` honored; toasts with actions stay until dismissed.

### Layout
- Group with space, not lines: inter-group gap ≥ 2× intra-group.
- Controls look interactive; never styled identically to static text.
- Align to shared edges; `padding-inline-start/end` for direction-dependent layout (RTL-safe).
- One spacing step per level of subordination; 12px between adjacent bordered/filled controls; 24px clearance around borderless icon-only controls.
- Breakpoints from content, not device presets; hold expanded layout until content genuinely breaks; container queries prefer.
- Growth/clipping: no fixed widths on text containers; rows wrap; critical actions never parked where resize/scroll clips them.

### Writing
- One voice; terms consistent ("Archive" never becomes "Move to storage"). Errors→calm plain tone; destructive confirmations repeat the consequence ("Delete project", never Yes/No).
- Plain words; delete filler; no idioms that won't translate (uz/ru/en awareness); skip unnecessary gender; verb-first buttons.
- Links describe destination (never bare "Learn more" ×N).
- One capitalization policy per element type.
- Errors say how to fix, adjacent to failing field; positive phrasing ("Use only letters", not "Don't use numbers").
- Empty states: orient + one next action ("No projects yet … Create a project"); filter empty states name query + offer exit.

### Typography
- ≤3 fonts; weights 400+ below 18px (<300 display-only). Type scale with semantic names; heading sizes descend with level.
- Line-height unitless: headings ~1.1, body 1.5–1.6 (anything wrapping 3+ lines ≥ 1.4).
- Measure capped 60–75ch. `text-wrap: balance` headings / `pretty` descriptions; `overflow-wrap: break-word` for long IDs; `nowrap` for badges.
- `tabular-nums` on changing values. Truncate without losing content (tooltip/expand for full value). Inputs 16px on mobile (iOS zoom); avoid `maximum-scale=1`.

### UI polish (assume motion belongs; recipes in order)
1. Concentric radius: outer = inner + padding (>24px padding → treat as separate surfaces).
2. Optical over geometric alignment (icon-side padding −2px; play triangles translateX(2px); fix asymmetric icons in SVG).
3. Borders = structure only; depth = layered transparent `box-shadow` (project brief overrides: border-not-shadow on cards).
4. Interruptible animations: CSS transitions for interactive state changes; keyframes only for one-shot stages.
5. Stagger rare entrances ~100ms (split title/sub/buttons); never stagger high-frequency.
6. Exits: small fixed `translateY`, softer than enters, `ease-out`.
7. Contextual icon animations: scale 0.25→1, opacity 0→1, blur 4→0, `cubic-bezier(0.2,0,0,1)` CSS or spring `duration 0.3, bounce 0` — exact values; both icons in DOM cross-fade if no motion lib.
8. Image outlines: 1px `black/10` light, `white/10` dark, `outline-offset:-1px`; never tinted neutral.
9. Scale on press: exactly `0.96`, never <0.95.
10. Never `transition: all`; exact properties. `will-change` only transform/opacity/filter, sparingly. `initial={false}` to prevent enter animation on load.
11. Icon stroke matches text weight (1.5px / 400, 2px / 600); one SVG recolored via `currentColor`.

## Report format (exact sections)

1. **Scope and Coverage** — mode (`quick`|`full`), exact scope, stack+styling conventions, boundary. Coverage table across all 5 domains (evidence inspected | count or `Clear`; `Not reviewed` must say why).
2. **Findings** — one table, ordered by severity, ranked by reach/leverage (token/shared-component fix > leaf):

   | # | Severity | Domain | Location | Before | After | Why |

   Severity: `HIGH` blocks a task / misleads / hides controls / data-loss / repeated systemic failure; `MEDIUM` meaningfully harms comprehension/efficiency/consistency; `LOW` isolated polish. Row = one root cause (all locations in one row). Finding caps: quick ≤5, full ≤15; never pad.
3. **Considered but Rejected** — 2–5 real candidates rejected (convention intentional, evidence insufficient, complexity without benefit).
4. **Verification** — what was checked, commands run, observed results; separate **Not verified**.
5. **Verdict** — exactly one: `Block` (HIGH remains) / `Needs changes` (MEDIUM/LOW) / `Approve`.

## Scope

`autodrive-frontend` full mode: shared surfaces (`src/components/ui`, `src/components/layout`, `src/components/filter`) + dashboard/students/payments flows; empty/loading/error/narrow states when present. Respect project tokens (`Card/StatCard/DataCard`, `border-border`, 4px grid, semantic colors) — findings may take token conflicts to the style brief explicitly.
