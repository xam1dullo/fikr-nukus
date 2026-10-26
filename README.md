# Fikr — patient feedback for the Nukus endocrinology center

Every staff member gets a QR poster. Patients scan it on their phone, answer a short survey (uz Cyrillic / uz Latin / Karakalpak / ru), and the chief doctor reviews the results at `/admin`.

| Path | What |
|---|---|
| [`app/`](app) | The product: Node server (`app.js`, MySQL), patient/admin pages (`public/`), `worker.js` for the Cloudflare demo. **Setup and env: [`app/README.md`](app/README.md).** |
| [`php/`](php) | **Production on Airnet cPanel** (no Node there): PHP 8.3 port of `app.js`, same URLs/JSON/pages. **Deploy: [`php/README.md`](php/README.md).** |
| [`.cpanel.yml`](.cpanel.yml) | cPanel Git deploy: copies `php/` + `app/public` + `app/seed` into the subdomain's document root |
| [`DESIGN.md`](DESIGN.md), [`ds.css`](ds.css), [`design-system.html`](design-system.html) | Design system |
| [`index.html`](index.html) | Product prototype |
| [`to-questionnaire-nukus-endo-feedback.md`](to-questionnaire-nukus-endo-feedback.md) | Requirements questionnaire |
| [`workflows/`](workflows) | Design/review agent workflows |

Quick start (Node):

```sh
cd app && cp .env.example .env && npm install && npm run dev
```

PHP: see [`php/README.md`](php/README.md) → Local. Survey text lives in `app/surveys.js`; after editing run `cd app && node export-surveys.js` (regenerates `php/surveys.json`; `npm test` fails if you forget).

Printed poster PDFs and deploy zips are not in git (`.gitignore`).
