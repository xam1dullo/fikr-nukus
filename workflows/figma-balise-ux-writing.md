# Workflow: figma-balise-ux-writing

Source skill: Figma Community/AI skill 64048 "balise-ux-writing" (UX writing review + rewrite). Codebase equivalent: audit all user-facing copy — which lives in `src/i18n/locales/{uz,ru,en}.json` due to project law (ALL strings via i18n).

## Loop

**What:** Audit interface copy for clarity, consistency, tone across buttons, forms/errors, empty states, onboarding, confirmations, settings, consent, system feedback. **Trigger:** event — new feature copy, locale sync PR; or schedule — after content changes. **Checkpoint:** per rewrite: meaning, voice/tone preserved; changes require fresh expert input.

## Audit spec (rubric)

### Review categories (find copy in all — quote key + value from all 3 locales)
1. Buttons/actions — verb-first, describe consequence ("Delete branch" not "OK"); dialog footer button = the action.
2. Forms/errors — error explains what's wrong + how to fix, adjacent to field; placeholder not used as label.
3. Empty/constrained states — new-user empty states orient + give a next step; filter/permission empty states explain what was blocked;
4. Onboarding/introductory — orient+connect features fast.
5. Confirmation dialogs — state consequence + outcome ("Delete branch? This will close its issues"), never generic Yes/No.
6. Settings/labels — describe what a setting does in plain terms.
7. Consent/policy/CTAs — meaningful, active choices.
8. System feedback (toasts/snackbars/loading) — transient, clear, not chatty; failures explain next step.

### Voice, tone, grammar (for uz, ru, en)
- Consistent voice & terminology (banking-grade: same word for same concept in all 3 locales — "branch" (filial) etc.); locale-appropriate formality (uz: consistent addressing policy; ru: consistent ты/вы; report the choice found, don't silently switch).
- Terminology consistency table: concept | uz | ru | en — flag drift.
- Numbers/phonetics: digits, punctuation, capitalization per locale.
- `vague words` flag: get/see/have (`olish`, `ko'rish`, "получить" patterns) + noun stacks; rewrite to concrete verbs.
- `statement_language` watch: uz copy must read as natural Uzbek — flag literal machine-translations from en (uncalqued phrasing, transliterated loans where established term exists).

### Boundaries
- Preserve meaning, voice/format, clear info hierarchy — full rewrite only when user signal is lost.
- Never introduce new features/capabilities via copy.
- Non-UI copy (email templates) explicitly listed as off-scope.
- Changes need fresh expert input: keep rewrite candidates as proposals, user approves.

### Domain/brand guard (project specifics)
- Domain mentions: autodrive domain vocabulary — course/lesson/student/group/attendance/payments terms audited for consistency between locales.
- Brand voice: professional, trustworthy, 经济/finance style; avoid bank-fintech cutesy nicknames (omit risky humor).
- Keep localized meanings/measurements/formats; flag locale-specific details that damage trust when localized (dates, currency format usage in copy vs UI formatting layer).

### Discovery method
- Pull copy from locale JSONs (source of truth), not components — but verify usage: which key renders where (a few spot-checks per claim) → hand writers context-aware rewrites.
- Detect the TOOLSET used to manage locales (key existence parity across uz/ru/en): missing-key report (en.json-only keys = silent fallback = finding).
- Keys with identical en/uz/ru values: check if literally same meaning in fact ("OK button" type vs untranslated placeholder).
- Flag hardcoded strings/i18n-missing strings in components (grep `t(`/`useTranslation`/raw string literals in JSX) — project law finding.

### Output (exact)
1. **Terminology consistency table** — concept | uz | ru | en | verdict per concept.
2. **Findings by category** — evidence: key + value across locales + usage screenshot-equivalent (`file:line` of component that renders it); tiered: clarity | consistency | tone | completeness.
3. **Rewrite proposals** — before/after per key, at most 10 – prioritized by user-impact (errors + destructive dialogs first). Preserving meaning, voice/tone per project traits. Meaning/format/consensus preserved explicitly noted per rule above.

## Scope

`autodrive-frontend/src/i18n/locales/*` + copy-rendering components. uz = primary locale (market), ru/en secondary but all three audited. Report key parity issues in the en↔ru↔uz direction separately, since i18n sync drift is a structural (not stylistic) problem.
