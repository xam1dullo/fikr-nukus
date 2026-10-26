# Fikr — PHP server (cPanel, no Node)

Same app as `app/app.js` (URLs, JSON, cookies, CSV), in plain PHP 8.3 + PDO MySQL, for Airnet cPanel where the Node.js Selector is disabled. `app/public/*.html` are used unchanged.

| File | What |
|---|---|
| `index.php` | front controller: router + all handlers |
| `surveys.php` | `grade()`, `device_info()`, `clean_phone()`; data from `surveys.json` |
| `surveys.json` | generated: `cd app && node export-surveys.js` after editing `surveys.js` (`npm test` fails if stale) |
| `.htaccess` | PHP 8.3 handler, `/a/*` files served by Apache with 30-day immutable cache, everything else → `index.php` |
| `config.example.php` | config template; the real file lives **outside** the web root |
| `hash.php` | run locally to make `ADMIN_HASH`; not deployed |
| `test.php` | `php php/test.php` — mirrors `app/grade.test.js` |

Differences from Node, on purpose: rate limits live in a MySQL table `hits` (no long-lived process); `ADMIN_HASH` is `password_hash()` (bcrypt), not scrypt; the schema check runs once per deploy (guard file `.migrated`, removed by every deploy); bodies over 32 KB get a `413` JSON reply (Node just closes the socket); photos are sent as `image/jpeg`.

## Deployed layout (document root)

```
index.php  surveys.php  surveys.json  .htaccess  .migrated
a/        ← app/public (css, js, fonts, photos, s.html + admin.html templates)
seed/staff.json
```

## cPanel steps (first time)

1. **Domains** → find `fikr.endork.uz` → check the **Document Root**. If it is not `/home/azama430/fikr.endork.uz`, change `DEPLOYPATH` in `.cpanel.yml` (repo root) first.
2. **MultiPHP Manager** → tick `fikr.endork.uz` → PHP 8.3 (`ea-php83`) → Apply. (`.htaccess` also carries the 8.3 handler, so deploys don't reset it.) **MultiPHP INI Editor**: nothing needed.
3. Config, on your computer: `php php/hash.php` → type the password (10+ chars) → copy the printed `'ADMIN_HASH' => '…',` line. `openssl rand -hex 32` → `SESSION_SECRET`.
4. **File Manager** → `/home/azama430/` (home, **not** the web root) → new file `fikr-config.php` → paste `config.example.php`, fill `DB_PASS`, `ADMIN_USER`, `ADMIN_HASH`, `SESSION_SECRET`. Permissions `600` (if the error log says "Permission denied", use `640`).
5. **Git Version Control** → `fikr-repo` → Manage → Pull or Deploy → *Update from Remote*, then *Deploy HEAD Commit*. (The checked-out branch must contain `php/` and `.cpanel.yml`.)
6. Open `https://fikr.endork.uz/admin`, log in. The first request creates the tables and seeds `seed/staff.json` if `staff` is empty. Xodimlar → print QR posters.

Updates: push → Git Version Control → Update from Remote → Deploy HEAD Commit.

Errors: cPanel → **Errors**, or the `error_log` file in the document root (not web-readable: routed to `index.php` → 404). Only the message and file:line are logged, not the stack trace, so patient phone numbers stay out of the log.

## Local

```sh
D=$(mktemp -d) && mkdir -p $D/a $D/seed && cp php/index.php php/surveys.php php/surveys.json $D/ && cp -R app/public/. $D/a/ && cp app/seed/staff.json $D/seed/
cp php/config.example.php $D/config.php   # fill in; DB from app/README.md "Local"; 'COOKIE_INSECURE' => true
FIKR_CONFIG=$D/config.php php -S localhost:8080 -t $D $D/index.php   # → http://localhost:8080/admin
php php/test.php
```
