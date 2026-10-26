# Fikr — per-staff QR patient feedback

Patients scan a staff member's QR → `/s/<code>` → answer the survey for that person's role → the chief doctor reviews everything monthly at `/admin`.

- `app.js` — node:http server, MySQL via `mysql2` (only dependency). Creates tables and seeds `seed/staff.json` on first start.
- `surveys.js` — the 7 surveys, copied from the center's Google Forms (uz + ru), and `grade()` validation.
- `public/s.html` — patient page (GSAP optional, works without it). `public/admin.html` — chief doctor panel, A4 QR posters.
- `worker.js` + `wrangler.jsonc` + `schema.sql` — same app on Cloudflare Workers + D1 (demo).
- `npm test` — survey, i18n, transliteration tests.
- Patient phone: optional, Uzbek only, stored as `+998XXXXXXXXX` in `response.phone` (column added automatically on start; D1: `ALTER TABLE response ADD COLUMN phone TEXT`). Admin lists show it masked, one tap reveals a `tel:` link; Excel export has it in full. It is personal data under ЗРУ-547, so real patients' numbers belong only on the UZ server (Airnet), never on the Cloudflare demo.

## Env

| Var | Example |
|---|---|
| `DB_HOST`, `DB_PORT` | `localhost`, `3306` |
| `DB_USER`, `DB_PASS`, `DB_NAME` | from cPanel → MySQL Databases |
| `ADMIN_USER` | login name |
| `ADMIN_NAME` | shown in the greeting, e.g. `J. Ubbiyev` (optional) |
| `ADMIN_HASH` | output of `npm run hash` (scrypt; the plain password is never stored) |
| `SESSION_SECRET` | 64 random hex chars; changing it logs everyone out |
| `PUBLIC_URL` | `https://fikr.endork.uz` (goes into the QR codes — set before printing) |
| `COOKIE_INSECURE` | `1` only for local http |
| `PORT` | `3000` (cPanel sets it itself) |

Copy `.env.example` → `.env` and fill it in. Generate the secret with `openssl rand -hex 32`, the hash with `npm run hash`.

## cPanel deploy (Airnet)

1. Domains → create subdomain `fikr.endork.uz`; SSL/TLS Status → run AutoSSL.
2. MySQL Databases → create DB + user, grant ALL.
3. Upload the `app/` folder (without `node_modules`, `.env`) to e.g. `~/fikr`.
4. Setup Node.js App → Create: Node 22, mode Production, root `fikr`, URL `fikr.endork.uz`, startup file `app.js`; add the env vars; Run NPM Install; Restart.
5. Open `https://fikr.endork.uz/admin`, log in, Xodimlar → print QR posters.

## Cloudflare demo (Workers + D1)

Demo only — no real patients' phone numbers here (see above).

1. `npx wrangler login`
2. New account: `npx wrangler d1 create fikr-db`, put the id into `wrangler.jsonc`, then `npx wrangler d1 execute fikr-db --remote --file schema.sql`.
3. Secrets: `npx wrangler secret put ADMIN_HASH`, `… SESSION_SECRET`, `… PUBLIC_URL`. `ADMIN_USER`, `ADMIN_NAME` are in `wrangler.jsonc` → `vars`.
4. `npm run cf:deploy`. Bump `ASSET_VERSION` in `wrangler.jsonc` when `public/` changes (cache busting).
5. Staff are added in `/admin` → Xodimlar (D1 is not auto-seeded from `seed/staff.json`).

## Local

`docker run -d --name fikr-db -e MARIADB_ROOT_PASSWORD=x -e MARIADB_DATABASE=fikr -e MARIADB_USER=fikr -e MARIADB_PASSWORD=fikrpw -p 33306:3306 mariadb:10.6`, `cp .env.example .env` (DB_PORT=33306, COOKIE_INSECURE=1), `npm install`, `npm run dev` → http://localhost:3000/admin.

CI: `.github/workflows/test.yml` runs `npm test` on every push.
