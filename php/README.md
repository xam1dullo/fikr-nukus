# Fikr — PHP server (Airnet cPanel production)

Port of `app/app.js` to plain PHP 8.3 + MySQL (no framework, no Composer). Same URLs, JSON, cookies; `public/s.html` and `public/admin.html` are served unchanged. Differences from Node:

- `ADMIN_HASH` is PHP `password_hash()` output, not scrypt (`npm run hash` hashes don't work here).
- Rate limits live in MySQL table `hits` (no in-memory state between requests).
- Asset version `{{V}}` = newest file mtime in `a/` (no `ASSET_VERSION` to bump).

## Files

| File | What |
|---|---|
| `index.php` | Front controller: router, all handlers, migrations, staff seed |
| `surveys.php` | `grade()`, `deviceInfo()`, `cleanPhone()` — ported from `app/surveys.js` |
| `surveys.json` | Survey texts. **Generated** by `node app/export-surveys.js` — don't edit by hand |
| `.htaccess` | Rewrites everything except real files under `/a/` to `index.php`; denies dot-files |
| `config.example.php` | Config template (not deployed) |
| `hash.php` | Makes `ADMIN_HASH` — CLI or one-off browser page (not deployed) |
| `test.php` | `php php/test.php` — grade/device/phone tests (not deployed) |
| `../.cpanel.yml` | cPanel Git deploy: copies files into `public_html/fikr.endork.uz/` |

Deployed layout: `index.php`, `surveys.php`, `surveys.json`, `.htaccess`, `a/` ← `app/public/*`, `seed/staff.json`, `.migrated` (written after migrations ran).

## Config

`/home/azama430/fikr-config.php` — outside `public_html`, chmod 600. Returns a PHP array (see `config.example.php`); path can be overridden with env `FIKR_CONFIG`.

| Key | Example |
|---|---|
| `DB_HOST`, `DB_PORT` | `localhost`, `3306` |
| `DB_USER`, `DB_PASS`, `DB_NAME` | `azama430_fikr`, …, `azama430_fikr` |
| `ADMIN_USER` | login name |
| `ADMIN_NAME` | shown in the greeting, e.g. `J. Ubbiyev` (optional) |
| `ADMIN_HASH` | output of `hash.php` (the plain password is never stored) |
| `SESSION_SECRET` | 64 random hex chars (`openssl rand -hex 32`); changing it logs everyone out |
| `PUBLIC_URL` | `https://fikr.endork.uz` (goes into the QR codes — set before printing) |
| `COOKIE_INSECURE` | `true` only for local http, otherwise `false` |

## cPanel deploy (Airnet)

1. MultiPHP Manager → tick `fikr.endork.uz` → PHP 8.3 (`ea-php83`) → Apply. (Default is 7.4 — won't run.)
2. MySQL Databases → DB `azama430_fikr` + user `azama430_fikr`, ALL PRIVILEGES. (Already done.)
3. `ADMIN_HASH`: File Manager → upload `php/hash.php` into `public_html/fikr.endork.uz/` → open `https://fikr.endork.uz/hash.php` → type the password (≥10 chars) → copy the `ADMIN_HASH` line → **delete `hash.php` immediately**. (Or locally: `php php/hash.php`.)
4. Config: File Manager → home dir `/home/azama430` (**not** `public_html`) → + File `fikr-config.php` → paste `config.example.php`, fill in `DB_PASS` (step 2), `ADMIN_HASH` (step 3), `SESSION_SECRET` (64 hex) → Permissions `600`.
5. Git Version Control → `fikr-repo` → Manage → Pull or Deploy → Update from Remote → Deploy HEAD Commit. This runs `.cpanel.yml` and copies into `public_html/fikr.endork.uz/`. The checked-out branch must be the one with `php/` (`php-port` until merged).
6. Open `https://fikr.endork.uz/admin` → log in → Xodimlar → check staff are there (81, seeded from `seed/staff.json` on the first request) → print QR posters.

Updating: push to GitHub → Git Version Control → Update from Remote → Deploy HEAD Commit.
Survey text change: edit `app/surveys.js`, run `node app/export-surveys.js`, commit both (`--check` fails if `surveys.json` is stale).

## Troubleshooting

- 500 → cPanel → Errors, or `error_log` in the docroot.
- "config" error → `/home/azama430/fikr-config.php` missing, unreadable or not `return [...]`.
- Tables missing / schema changed → delete `.migrated` in the docroot; migrations re-run on the next request.

## Local

`docker run -d --name fikr-db -e MARIADB_ROOT_PASSWORD=x -e MARIADB_DATABASE=fikr -e MARIADB_USER=fikr -e MARIADB_PASSWORD=fikrpw -p 33306:3306 mariadb:10.6`, then copy `config.example.php` somewhere (DB_HOST `127.0.0.1`, DB_PORT `33306`, `COOKIE_INSECURE` `true`) and build the layout:

```sh
B=/tmp/fikr-build; rm -rf $B; mkdir -p $B/a $B/seed
cp php/index.php php/surveys.php php/surveys.json php/.htaccess $B/
cp -r app/public/. $B/a/
cp app/seed/staff.json $B/seed/
FIKR_CONFIG=/path/config.php php -S localhost:8080 -t $B $B/index.php
```

→ http://localhost:8080/admin. Tests: `php php/test.php`.

Patient phones are personal data under ЗРУ-547: real numbers live only on this UZ server (Airnet), never on the Cloudflare demo.
