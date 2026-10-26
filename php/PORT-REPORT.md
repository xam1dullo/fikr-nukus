# PHP port — hisobot (2026-10-26, branch `php-port`, commit qilinmagan)

## Nima o'zgardi
- `php/index.php` — `app/app.js` ning to'liq porti: barcha route'lar, migrate (+ `hits` jadvali), HMAC sessiya, `d` cookie, rate limit (MySQL), readJson 415/413/400, submit (409 dup, 422 phone, ip+UA fallback), barcha agregatlar, CSV eksport, xatolar (4xx o'tadi, 5xx → `{error:'server'}` + error_log).
- `php/surveys.php` — `grade()`, `device_info()`, `clean_phone()` porti; ma'lumot `php/surveys.json` dan.
- `app/export-surveys.js` — `surveys.js` → `php/surveys.json`. `app/surveys-json.test.js` — json eskirsa `npm test` yiqiladi. (`app/` dagi boshqa fayllar tegilmagan.)
- `php/.htaccess`, `php/config.example.php`, `php/hash.php` (lokal, deploy qilinmaydi), `php/test.php`, `php/README.md`, `.cpanel.yml`, root `README.md`.

## Ataylab farqlar
- `ADMIN_HASH` endi bcrypt (`php php/hash.php`), eski scrypt hash ishlamaydi.
- 32 KB dan katta body → PHP `413 {"error":"big"}` qaytaradi (Node socketni yopardi).
- `.jpg` endi `image/jpeg` (Node `application/octet-stream` berardi).
- Migrate har so'rovda emas: `.migrated` guard fayl, har deploy o'chiradi → deploydan keyin 1 marta ishlaydi (`GET_LOCK` bilan, ikki marta seed bo'lmaydi).
- `.htaccess` ichida cPanel `ea-php83` handler bloki bor — aks holda har deploy `.htaccess` ni almashtirib PHP 7.4 ga qaytarardi.
- Log'ga faqat xabar + fayl:qator yoziladi (trace'da bemor telefoni chiqishi mumkin edi).

## Qanday tekshirildi
- `php -l` — 6 ta PHP fayl xatosiz. `php php/test.php` — 25/25 ok (grade, device, phone, Math.round, trim/cut). `cd app && npm test` — 12/12 ok.
- Lokal MariaDB 11.4 (nix, vaqtinchalik datadir) + Node (`app.js`, :3999) + PHP (`php -S`, :8080) **bitta DB** da:
  - 13 ta endpoint (`/api/stats` 4 oy, `/api/staff/<id>`, `/api/comments`, ikkala CSV eksport) — javob **baytma-bayt bir xil** (status, Content-Type, Content-Disposition, body).
  - Chegaraviy holatlar ikkalasida bir xil: NPS −12.5 → −12, avg 3.165 → 3.17, joriy oy uchun qisman oldingi oy (`until`), `/api/me`.
  - Sessiya cookie o'zaro mos (PHP cookie Node'da 200, aksincha ham). Cookie'siz fallback device id bir xil (Node PHP yozgan qatorga 409 berdi).
  - Xato yo'llari bir xil: 409, 422, 400 answers/json, 415, 404 staff, 401, login 8 urinishdan keyin 429.
- Haqiqiy Apache 2.4 + PHP-FPM 8.3 (nix) bilan `.htaccess`: `/a/*` → 200 + `immutable` kesh + SEC header'lar; `/a/s.html`, `/seed/…`, `/surveys.json`, `/surveys.php`, `/index.php`, `/.migrated`, `/.htaccess` → 404; `/` → 302 `/admin`.
- Brauzer (Chrome, Apache ustida): login → dashboard → `/s/<kod>` (kassa) → 6 savol + izoh + telefon → "Rahmat!" → izoh admin'da va CSV'da (`5,00`, `91 234 56 78`), qayta yuborish 409.
- Tekshirilmagan: haqiqiy Airnet server (SSH yo'q), cPanel Git deploy, MultiPHP.

## Ochiq savollar
1. `fikr.endork.uz` Document Root aniq `/home/azama430/fikr.endork.uz` mi? (`.cpanel.yml` shunga yozilgan; cPanel → Domains'da tekshiring.)
2. cPanel'dagi repo qaysi branch'da? `php-port` ni `main` ga merge qilamizmi yoki cPanel'da branch almashtiriladimi?
3. X-Forwarded-For'ga ishonish (Node kabi) — Airnet oldida proxy yo'q bo'lsa, rate limitni aylanib o'tish mumkin. Faqat `REMOTE_ADDR` ishlatsakmi?
4. Node + `worker.js` endi kerakmi, yoki prod faqat PHP bo'ladimi?
