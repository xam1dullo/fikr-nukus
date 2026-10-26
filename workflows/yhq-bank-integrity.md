# Workflow: yhq-bank-integrity

Keeps the public B-category question bank (student.automaktab.uz) correct against
the law and keeps tickets at 20 questions. Replaces the manual 2026-10-18 loop:
audit → fix migration → verify live → regroup tickets.

## Loop

**What:** detect changes in the law or the live bank, audit what changed, prepare
fix PRs (text, chapter, answer key, ticket regroup), and hand the user one brief.

**Trigger:**
- **Daily probe** — schedule, 09:00 Asia/Tashkent. Deterministic, no AI, ~10 s.
  Fires a full run only when the law or the bank fingerprint changed.
- **Weekly full run** — schedule, Sunday 21:00 Asia/Tashkent. Runs even with no
  change (backstop for anything the probe misses).

**Checkpoint:** exactly one, pushed right — after all PRs are open and tested.
The user gets a Linear issue (brief) and merges the PRs themself. Nothing merges
automatically.

**Runs on:** the user's Mac, as local scheduled tasks (`scheduled-tasks` MCP,
`create_scheduled_task`). Reads only the public API and lex.uz. Never touches
the production database (no SSH, no `DATABASE_URL` writes).

## Sources

| Source | Where | Notes |
|---|---|---|
| Law (YHQ, VMQ 172, 12.04.2022) | `https://lex.uz/docs/-5953883` | Canonical. Strip lex.uz UI noise lines (see Gotchas). |
| Law snapshot | `audits/yhq-citation-audit-2026-10-18/yhq-2022-172-full.json` (`chapters`, `clauses`, `signs`, `marks`) and `yhq-2022-172-clauses.json` | Baseline for clause diffs. Each run writes a new snapshot to state. |
| Live bank | `GET https://api.automaktab.uz/questions/practice?locale=uz&page=N&limit=100` | Header `origin: https://student.automaktab.uz`. Fields: `question_id, version_id, version_number, topic, ticket_number, locale, stem, options[{option_key,text}], diagram_media_id`. Paging is stable (orderBy publishedAt, id). |
| Answer key + explanation | `POST /questions/practice/:id/check` body `{"option_key":"A","locale":"uz"}` → `correct_option_key, explanation` | Throttled 20/min: sleep ≥ 3.2 s between calls. Call only for questions in the audit set. |
| Tickets | `GET /questions/practice/tickets?page=1&limit=60` → `{data:[{number,question_count}],meta}` | Expect 55 × 20 after the 2026-10-18 regroup. |
| Diagrams | `scraping/data/images` (seed images) and the media URL behind `diagram_media_id` | Needed for answer-key checks on sign/diagram questions. |
| Prior audit | `audits/yhq-citation-audit-2026-10-18/{xatolar-ro-yxati.csv, avtomatik-tekshiruv.csv, subagent-results/}` | Known open items; do not re-report as new. |

## Code home

Built 2026-10-18 in `autodrive-backend/scripts/bank-integrity/` (branch
`feat/bank-integrity-scripts`, worktree `autodrive-backend-bank-integrity` until merged).
Python stdlib only, run with `python3 -I`, read-only against the API.

- `bank.py` — shared: export, tickets, media, `/check` cache by `version_id`, lex.uz fetch
  (one line per `<div name="-id" id="-id">`, skips OKOZ/edit-history blocks) + parser, state I/O.
  `BANK_INTEGRITY_HOME` overrides the state dir (use a temp dir for tests).
- `probe.py` — daily check. Exit 0 unchanged, 10 changed (`init`/`law`/`bank`), 2 lex parse broken.
- `audit.py` — full run, deterministic part: findings over the whole bank, audit set,
  images, batches `a_NN.json` / `b_NN.json` (two different groupings of 10 for the two
  independent reviews), `summary.json`, `state_next.json`. `--max-checks N`, `--all`,
  `--commit <run>` saves the baseline (only after the brief is handled).
- `regroup.py --out DIR [--migration REPO]` — deals tickets, writes `mapping.csv`; with
  `--migration` renders the all-or-nothing regroup migration (skips when nothing moves).
- Text/key fix migrations are written by the agent from the two templates (judgement needed).
- Verify after merge = next probe sees the new fingerprint + `tickets()` sizes; for a fix
  PR, re-run `audit.py` and confirm the fixed rows no longer appear.

Scheduled tasks (Auto mode): `yhq-bank-probe` (daily 09:00, notify off) and
`yhq-bank-weekly` (Sunday 21:00), files in `~/.claude/scheduled-tasks/`.

## State

`~/.automaktab/bank-integrity/` (outside every repo):
- `state.json` — `{lex_hash, lex_fetched_at, bank_fingerprint, bank_count, last_full_run, last_issue}`.
- `lex/<date>.json` — clause snapshot per change.
- `runs/<YYYY-MM-DD-HHMM>/` — export, findings CSV, brief draft, logs.

**Bank fingerprint** = sha256 over sorted `question_id:version_id:ticket_number`
lines from the full export. **Lex hash** = sha256 of the noise-stripped clause text.

## Steps

### 1. Daily probe (no AI)
1. Fetch lex.uz page, strip noise, hash. Export bank, fingerprint.
2. Both unchanged → append one line to `runs/probe.log` and stop.
3. Either changed → start a full run with the reason (`law`, `bank`, or both).

### 2. Full run
1. **Export** the live bank (all pages). Assert unique ids.
2. **Audit set** = questions that are new or have a new `version_id` since the last run
   + questions whose explanation cites a clause whose text changed.
   Weekly run with no change: empty AI set (deterministic checks still cover the whole bank).
3. **Deterministic checks on the whole bank** (`audit_live.py`):
   truncated options (leading fragment like `ga va chapga`), explanation cites a band
   under the wrong chapter, sign/marking number missing from annexes, missing explanation,
   ticket size ≠ 20, more than 1 unticketed published question.
4. **AI review on the audit set only.** Batches of ~10, one subagent per batch, in parallel.
   Each returns per question: `verdict ∈ {OK, WRONG_KEY, AMBIGUOUS, TEXT_BROKEN, EXPLANATION_WRONG, UNVERIFIABLE}`,
   the quoted clause (chapter + band number + text), and for WRONG_KEY the proposed key.
5. **Answer-key acceptance rule** (all must hold, else it goes to the methodologist list):
   - two independent subagents (different batch, no shared context) propose the same new key;
   - both quote the same governing clause from the current lex snapshot;
   - for diagram/sign questions, both state what the image shows (sign number, lanes, vehicles);
   - the proposed option exists in the question's `uz` options;
   - no offered option is correct → never a key change: becomes an `in_review` pull with a reason.
6. **Fix PR (backend)** in a fresh worktree from `origin/main`, branch `fix/yhq-bank-<date>`:
   - text/chapter fixes → template `prisma/migrations/20261018120000_correct_yhq_bank_text`;
   - key fixes and `in_review` pulls → template `20261018140000_correct_yhq_bank_answer_keys`;
   - every row guarded by id + audited old value; published versions immutable → new version per question;
   - one migration spec per migration, same style as the template specs.
7. **Ticket regroup** — only if the published pool changed size or membership: run `regroup.py`,
   render a migration from template `20261018160000_regroup_practice_tickets_of_20`
   (all-or-nothing guard, in_review tickets cleared). Same branch as step 6 if both exist.
8. **Test on a scratch Postgres** (never the real one):
   `docker run -d --rm --name scratch-pg-bank -e POSTGRES_PASSWORD=scratch -e POSTGRES_DB=scratch -p 55432:5432 postgres:15-alpine`;
   `DATABASE_URL=postgresql://postgres:scratch@localhost:55432/scratch?schema=public pnpm exec prisma migrate deploy`;
   seed synthetic rows from the export and assert: fixes applied, guard skips a drifted row,
   re-run is a no-op, empty bank no-op. Then `vitest run src/modules/questions` and `pnpm run typecheck`.
   Remove the container.
9. **Open the PR** (`gh pr create --head … --base main`). Do not merge.
10. **Brief** → step 3.

### 3. Checkpoint: Linear issue
Create one issue in team **XAM** (Xam1dullo) per full run that found anything.
No findings → no issue; one line in `runs/probe.log`.

Title: `Savollar banki: <N> tuzatish PR'da, <M> metodist qarori — <date>`

Body (Uzbek, one screen):
- Nima o'zgardi: qonun / bank / ikkalasi, nechta savol tekshirildi.
- Avtomatik tuzatildi (PR havolasi): kesilgan variant N, bob N, kalit N (har biri: bilet/savol → eski→yangi, band).
- Metodist qarori kerak: `runs/<run>/metodist.csv` ga havola, soni turlar bo'yicha.
- Biletlar: 55×20 saqlandimi; qayta taqsimlash PR'da bo'lsa shu havola.
- Xavf: bank merge'gacha o'zgarsa migratsiya o'tkazib yuboriladi.
- Keyingi qadam: "PR'ni ko'rib merge qiling".

### 4. After merge (next probe)
The next daily probe sees the new fingerprint. If a fix PR from this workflow merged,
run `verify_live.py` for it and comment the result on the Linear issue
(applied / skipped-NOTICE → regenerate). Close the issue when everything applied.

## Definition of done (per run)
- Probe-only day: one log line, no issue.
- Full run: findings CSV in `runs/`, every auto-fix in an open, scratch-tested PR,
  every rejected key candidate on the methodologist list, one Linear issue, nothing merged.

## Do not
- Merge any PR, write to the production DB, or SSH anywhere.
- Change a key that fails any acceptance condition.
- Re-run the full AI review on the whole bank (only the audit set).
- Report items already listed in the 2026-10-18 audit as new.

## Gotchas (learned 2026-10-18)
- lex.uz noise to strip: `Ҳужжатга таклиф юбориш`, `Аудиони тинглаш`, `Ҳужжат элементидан ҳавола олиш`, `Oldingi tahrirga qarang.`
- Band parsing: `117.Temir…` has no space after the number.
- `1.16.2` is a road marking (annex 2), not a missing sign — false positive.
- Check endpoint 20/min; a 429 is a rate limit, not a broken question.
- A migration that skips on its guard is still recorded as applied; the fix needs a new migration.
- Seed `prisma/seed-scraped-questions.ts` still writes old 122-ticket numbers on a fresh DB.
- Backend worktree needs `pnpm run prisma:generate` (and a copied `.env`) before tests.
- 2026-10-18 first weekly run: a `Connection reset by peer` killed the hour-long cache fill
  (no retry), the agent had backgrounded it and ended its turn, and the run showed "succeeded"
  with 196/1101 keys cached and no baseline. Fixed: `_req` retries (5/15/30/60/120 s on
  network errors, 429, 5xx); tasks fill in foreground chunks (`audit.py --fill-only 150`,
  < 10 min each) and must report a non-zero exit as a failed run.
