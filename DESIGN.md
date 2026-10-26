# Fikr — Design System

Patient feedback for the Nukus endocrinology center: a QR survey (patients), a dashboard (chief doctor) and a printed A4 poster.

- **Tokens and components:** [`ds.css`](ds.css) is the only source of truth. Pages never define their own colors, sizes or shadows.
- **Visual board:** [`design-system.html`](design-system.html) (DS-00 … DS-05) renders every token and component from `ds.css`.
- **Product:** [`index.html`](index.html).

If this document and `ds.css` disagree, fix one of them in the same change.

---

## DS-00 Overview

### Who we design for
- **Patients**: often older (diabetes, thyroid), sometimes poor eyesight, standing in a corridor, one hand on the phone, 60 seconds of patience. Three scripts: Uzbek Cyrillic, Uzbek Latin, Karakalpak Latin.
- **Chief doctor / heads of department**: desktop, short visits several times a day. They need "what is on fire" in 5 seconds.

### Principles
1. **One tap per decision.** Rating questions advance automatically. No step asks for two things.
2. **Calm, not clinical.** Warm off-white surfaces, one green-teal accent, no pure-black walls of text. Red only means "a patient is unhappy".
3. **Numbers first, then words.** The dashboard leads with KPIs and the unanswered-complaints count. Charts come after.
4. **Readable from arm's length.** Patient text ≥ 16px, patient touch targets ≥ 56px, contrast ≥ 4.5:1 everywhere.
5. **Promise only what we keep.** No "answer within 24 h" copy unless the center commits to it.
6. **Tokens or nothing.** Every color, size, radius, shadow and duration comes from `ds.css`. A raw hex value in a page is a bug.

### General usage rules
- Light theme only. A dark theme is out of scope until the dashboard is used at night.
- One primary button per screen.
- Motion explains change (enter, select, count-up). It never decorates. `prefers-reduced-motion` turns it off.
- Mobile first for patient screens (360–430px), desktop first for the dashboard (≥ 1200px), but both must work everywhere.

---

## DS-01 Color

### Primitive palette

**Brand (teal-green):** calm, medical and friendly. It is not hospital-blue, and not the green used for "success".

| Token | Hex | Typical use |
|---|---|---|
| `--brand-50` | `#EEF8F5` | selected background, info panels |
| `--brand-100` | `#D7EFE7` | chart bars, hover fill |
| `--brand-200` | `#AEDFD0` | |
| `--brand-300` | `#7DC8B2` | hover border on options |
| `--brand-400` | `#45A98E` | |
| `--brand-500` | `#1E8C72` | |
| `--brand-600` | `#0F7560` | **accent**: primary buttons, links, active states (5.6:1 on white) |
| `--brand-700` | `#0B5E4E` | accent hover, text on `--brand-50` (7.1:1) |
| `--brand-800` | `#094A3E` | |
| `--brand-900` | `#073B32` | |

**Neutral (green-tinted gray):** a slight green tint keeps the grays from looking dead next to the brand color.

| Token | Hex | Typical use |
|---|---|---|
| `--n-0` | `#FFFFFF` | surfaces |
| `--n-25` | `#FAFBFA` | row hover |
| `--n-50` | `#F4F6F5` | page background |
| `--n-100` | `#ECEFEE` | sunken surfaces, ghost buttons |
| `--n-200` | `#DCE1DF` | ghost hover |
| `--n-300` | `#C2C9C6` | hover borders |
| `--n-400` | `#97A19D` | decorative only, never text |
| `--n-500` | `#67726E` | tertiary text (4.99:1) |
| `--n-600` | `#525D59` | secondary text (6.8:1) |
| `--n-700` | `#3A4441` | |
| `--n-800` | `#232B28` | |
| `--n-900` | `#121916` | primary text, inverse surfaces |

**Status:** each status has one `-50` background and one `-600` foreground.

| Status | `-50` | `-600` | Contrast of 600 on 50 |
|---|---|---|---|
| success | `#E6F4EC` | `#18794E` | 4.8:1 |
| warning | `#FDF5E1` | `#95600A` | ≥ 4.5:1 |
| danger | `#FDEEEA` | `#C23A22` | 4.7:1 |
| info | `#EDF1FD` | `#3B5BDB` | ≥ 4.5:1 |

**Rating scale (1–5):** used for the patient faces, score chips and bars. Every value passes 4.5:1 on white, so it is safe for text too.

| 1 | 2 | 3 | 4 | 5 |
|---|---|---|---|---|
| `#B91C1C` | `#C2410C` | `#A16207` | `#15803D` | `#0F7560` |

> The old prototype used `#CA8A04` (2.9:1) and `#7D8A86` (3.6:1) for text. Both failed WCAG AA and were replaced.

### Semantic roles
Components use **only** these roles. Primitives appear only inside `:root` and in the tables above.

| Role | Maps to | Use |
|---|---|---|
| `--bg` | `n-50` | page background |
| `--surface` | `n-0` | cards, inputs, phone screen |
| `--surface-sunken` | `n-100` | segmented control track, ghost buttons, quotes |
| `--surface-inverse` | `n-900` | toast, selected chip |
| `--text` | `n-900` | headings, body |
| `--text-2` | `n-600` | secondary text, labels |
| `--text-3` | `n-500` | captions, timestamps, placeholders |
| `--text-inverse` | `n-0` | text on inverse or accent |
| `--border` | 8% ink | card outlines, dividers |
| `--border-strong` | 16% ink | inputs, options, chips |
| `--accent` / `--accent-hover` | `brand-600` / `brand-700` | primary action, active state |
| `--accent-soft` | `brand-50` | selected row or option |
| `--on-accent` | `n-0` | text on accent |
| `--focus-ring` | 3px, 22% brand | every focusable element |

### Interactive states
| State | Rule |
|---|---|
| Hover | One step darker fill (`accent` → `accent-hover`) or a stronger border. Never a color change of the text alone. |
| Active / press | `transform: scale(.96)` on buttons, `.98` on wide rows, `.94` on rating faces. |
| Focus | `--focus-ring` via `:focus-visible`. Never remove it without a replacement. |
| Selected | `--accent-soft` fill, `--accent` border, ring. |
| Disabled | 40% opacity, `not-allowed` cursor, no shadow. Do not gray out the text color separately. |
| Error | `--danger-600` border, a ring at 14%, and a hint text below explaining the fix. |

### Color rules
- At most one accent-filled element per view (the primary CTA).
- Red (`danger`, `rate-1`) is reserved for complaints and low scores. Never use it for branding or decoration.
- Do not put status colors on large areas. Use the `-50` background with `-600` text.
- Charts use `--brand-100` bars with a `--brand-600` line. Scores use the rating scale.

---

## DS-02 Typography

**Typeface:** [Onest](https://fonts.google.com/specimen/Onest) 400/500/600/700/800. It covers Latin, Latin-ext and Cyrillic and has a modern, friendly geometric look. The fallback is `system-ui`.
> Before launch, check that Karakalpak glyphs (`ǵ ń ı á ó ú`) render in Onest. Missing glyphs fall back to the system font, which looks uneven.

### Type scale
| Role | Class / token | Size / line-height | Weight | Tracking | Where |
|---|---|---|---|---|---|
| Display | `.t-display` | clamp(34→52) / 1.05 | 800 | −3% | landing hero |
| H1 | `.t-h1` | 30 / 1.2 (26 mobile) | 700 | −2% | page title |
| H2 | `.t-h2` | 24 / 1.2 (22 mobile) | 700 | −2% | survey question, section title |
| H3 | `.t-h3` | 18 / 1.35 | 600 | −1% | sub-section |
| Title | `.t-title` | 15 / 1.4 | 600 | 0 | card titles |
| KPI | `.t-kpi` | 34 / 1.1 (28 mobile) | 700 | −3% | dashboard numbers, tabular |
| Body large | `.t-body-lg` | 17 / 1.55 | 400 | 0 | hero lead |
| Patient body | `.t-patient` | 16 / 1.5 | 400–600 | 0 | **minimum** on patient screens |
| Body | `.t-body` | 15 / 1.5 | 400 | 0 | dashboard default |
| Label | `.t-label` | 13 / 1.4 | 600 | 0 | field labels, tabs, small buttons |
| Caption | `.t-caption` | 12 / 1.35 | 500 | 0 | timestamps, hints. **Nothing smaller than 12px.** |

### Typography rules
- Headings use `text-wrap: balance`, and short paragraphs use `pretty`.
- Every changing number (KPIs, counters, ticket IDs, phones) uses `font-variant-numeric: tabular-nums` (`.num`).
- Use a maximum of 3 sizes per card.
- Line length: 45–70 characters for prose (`max-width: 46ch` on leads).
- Do not use all caps, except on the printed poster's small label line, with +8% tracking.

### Writing rules
- **Address patients politely** with the polite "you" form (siz / сиз). Use short sentences: one idea per sentence, one question per screen.
- **Verbs on buttons:** use "Davom etish", "Yuborish", "Javob yuborish". Avoid vague labels like "OK" or "Tasdiqlash".
- **No promises we cannot verify.** Wording like "24 soat ichida" needs the center's confirmation.
- **Scripts:** Uzbek Cyrillic follows official spelling (ў, қ, ғ, ҳ). For Uzbek Latin, prefer `oʻ gʻ` (U+02BB) and `ʼ` (U+02BC) over the ASCII `'`. *The prototype still uses ASCII; migrate the strings before launch.*
- **Karakalpak:** every string must be reviewed by a native speaker before release.
- **Numbers:** write `4.3 / 5` and `412`, and use a thin space for thousands (`1 240`). Times are 24h (`09:42`).
- **Empty states** say what happened and what is next: "Hammasiga javob berilgan ✓", not "Ma'lumot yo'q".
- **Dashboard tone:** neutral and factual. Never shame staff in the UI copy.

---

## DS-03 Foundations

### Spacing
Use a 4px grid. Only these values are allowed for padding, margin and gap.

| Token | px | Typical use |
|---|---|---|
| `--sp-1` | 4 | icon–text gap in badges, segmented track padding |
| `--sp-2` | 8 | gap between related items (chips, faces, option rows) |
| `--sp-3` | 12 | grid gap between cards, list-row vertical padding |
| `--sp-4` | 16 | control padding, mobile gutter, card padding (mobile) |
| `--sp-5` | 20 | card padding (desktop) |
| `--sp-6` | 24 | phone-screen side padding, header → content |
| `--sp-8` | 32 | desktop gutter, block spacing inside a view |
| `--sp-10` | 40 | |
| `--sp-12` | 48 | between major sections |
| `--sp-16` | 64 | page bottom padding, hero spacing |
| `--sp-20` | 80 | design board section spacing |

**Spacing rules**
- **Proximity:** the space inside a group is always smaller than the space between groups (8 inside → 24 between).
- **Card padding:** 20 on desktop, 16 under 560px. Card title → content: 16.
- **Page gutter (`--gutter`):** 32 above 1020px, 24 above 560px, 16 below 560px.
- **Dashboard grid gap:** 12.
- **Patient screen:** 24 side padding, 8 between options, 24 under the question title.
- **No odd values.** Values like 14px or 18px are not allowed. Exceptions: 1–3px hairlines and the 2px badge vertical padding.

### Radius
| Token | px | Use |
|---|---|---|
| `--r-xs` | 6 | small buttons, tabs, score chips |
| `--r-sm` | 10 | buttons, segmented items, toast |
| `--r-md` | 14 | inputs, option rows, faces, segmented track |
| `--r-lg` | 20 | cards |
| `--r-xl` | 28 | large hero panels |
| `--r-full` | 999 | badges, chips, avatars, progress |

**Concentric rule:** an outer radius equals the inner radius plus the padding between them. Example: segmented track 14 = item 10 + padding 4.

### Elevation
| Token | Use |
|---|---|
| `--sh-1` | resting cards, secondary buttons, selected segment |
| `--sh-2` | hovered or raised cards, dropdowns |
| `--sh-3` | phone mockup, poster, toast, modals |
| `--sh-accent` | primary button only (colored glow + inset highlight) |

**Elevation rules**
- **Borders separate, shadows lift.** Cards use both: a 1px `--border` and `--sh-1`.
- Shadows are always transparent ink, never pure black and never colored, except `--sh-accent`.

### Motion
| Token | ms | Use |
|---|---|---|
| `--dur-1` | 150 | hover, press, color changes |
| `--dur-2` | 250 | segmented pill, toast, icon swaps |
| `--dur-3` | 400 | screen-to-screen in the survey |
| `--dur-4` | 700 | count-ups, chart draw, first-load stagger |

Easing: `--ease-out` for nearly everything, `--ease-in-out` for looping or two-way moves. GSAP uses `power3.out`, which is close to `--ease-out`.

**Motion rules**
- **Enter:** opacity combined with a 12px rise and a 4px blur. **Exit:** shorter, or none.
- Stagger lists by 40–80ms.
- **Interruptible:** use CSS transitions for state changes, GSAP only for one-shot sequences.
- Never use `transition: all`.
- `prefers-reduced-motion` sets every duration to 0.

### Layout
- **Breakpoints:** `560px` (phone) and `1020px` (tablet). Content max width `--container` is 1200px.
- **Control heights:** `--h-sm` 36 (dashboard tabs), `--h-md` 44 (default, minimum touch target), `--h-lg` 56 (patient actions).
- **Z-index:** `--z-sticky` 10 (top bar), `--z-toast` 50.

---

## DS-04 Component usage

### General anatomy rule
Every interactive component has a **container** (fill, border, radius), a **content** (label, optional leading icon 16–20px, stroke 2–2.4), and **states** (default, hover, active, focus, disabled, plus selected or error where relevant).

| Component | Anatomy | Behavior |
|---|---|---|
| **Button** `.btn` | container + label (+ icon, gap 8) | Variants: `primary` (one per view), `secondary` (outlined alternative), `ghost` (low emphasis, inline), `danger` (destructive). Sizes: `sm` 36 for dashboard toolbars, default 44, `lg` 56 for patient CTAs (full width, sticky bottom). Press scales to 0.96. Disabled while a required answer is missing. |
| **Text field** `.field` | label → input → hint | The label is always visible; a placeholder is never the only label. The hint explains format or privacy. In the error state, the hint turns red and says how to fix it. Phone inputs use `type="tel"` and `inputmode="tel"`; the patient phone field is optional, shows a fixed `+998` prefix, formats as `90 123 45 67`, and rejects a partial number with an inline hint (blank is fine). |
| **Badge** `.badge` | pill + optional dot + text | Status only, not clickable. A dot means "new / unread". Complaint = danger, idea = info, thanks = success, neutral for metadata. |
| **Chip** `.chip` | pill toggle | Single choice in a small set (complaint, idea, thanks). Tapping it again deselects it. Selected = inverse fill, and the complaint chip uses danger. |
| **Option row** `.opt` | radio dot + label + meta | Patient single choice (language, department). Full width, 56px tall, 8px between rows. Language selection auto-advances. |
| **Rating face** `.face` | face icon + label | 5 equal columns, colored by the rating scale. Selecting one auto-advances after 400ms (the user can still go back). |
| **Segmented nav** `.seg` | track + sliding pill + items | Top-level view switch (2–4 items). The pill slides in `--dur-2`. |
| **Tabs** `.tab` | text buttons | Filters inside a card (All, Complaints, Unanswered). |
| **List item** `.list-item` | meta row (score, badge, dept, time) + 2-line preview | A 3px accent bar plus the soft fill marks the current item. Clamp the preview to 2 lines. |
| **Card** `.card` | head (title + caption) + body | One topic per card. Never nest a card inside a card. |
| **KPI** | label → value (`.t-kpi`) → delta | A delta is always a comparison with the previous period. Green = better for the patient (a lower response time is better and is shown green with ↓). |
| **Toast** | inverse pill, bottom center | Confirms an action for 2.2s and announces it via `role="status"`. Never use it for errors that need action. |

### Do / Don't
- ✅ Patient flow: question → faces → auto-advance. ❌ Do not add a "Next" button the user must press after every rating.
- ✅ Inline helper text under a field. ❌ Do not use tooltip-only help on mobile.
- ✅ One red KPI ("Javobsiz shikoyatlar") when the count is above 0. ❌ Do not use red for decoration.
- ✅ Show the anonymous state explicitly ("Anonim bemor"). ❌ Do not show a full phone number: mask it as `+998 91 *** ** 14`.

---

## DS-05 Components

The live library with every state is in [`design-system.html`](design-system.html) → **DS-05**. It includes:
- **Buttons:** primary, secondary, ghost and danger, each with default, hover, focus and disabled states, plus sizes sm, md and lg.
- **Text fields:** default, hover, focus, filled, error and disabled.
- **Badges:** neutral, accent, success, warning, danger, info and dot.
- **Navigation:** segmented control (default and hover), tabs (default, hover, selected, disabled) and list items (default, hover, current).
- **Patient controls:** option rows (default, hover, selected), rating faces 1–5 and chips.

State classes `.is-hover`, `.is-focus`, `.is-active` and `.is-disabled` exist only so the board can show states statically. Product code relies on real pseudo-classes.

---

## Checklist before shipping a screen
- [ ] No raw hex, px font size or shadow outside `ds.css`.
- [ ] Every padding, margin and gap is a `--sp-*` token.
- [ ] Text contrast ≥ 4.5:1, and nothing below 12px.
- [ ] Patient screens: text ≥ 16px and actions ≥ 56px.
- [ ] Every interactive element has a visible `:focus-visible` style.
- [ ] Numbers use `.num`.
- [ ] Animations respect `prefers-reduced-motion`.
- [ ] All 3 language strings fit at 360px width.
