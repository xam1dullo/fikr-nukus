# Fikr — patient feedback for the Nukus endocrinology center

Every staff member gets a QR poster. Patients scan it on their phone, answer a short survey (uz Cyrillic / uz Latin / Karakalpak / ru), and the chief doctor reviews the results at `/admin`.

| Path | What |
|---|---|
| [`php/`](php) | Production server for Airnet cPanel (plain PHP 8.3 + MySQL, no Node needed — the host has Node.js Selector off). **Setup and deploy: [`php/README.md`](php/README.md).** |
| [`app/`](app) | Node server `app.js` — reference implementation and local dev; `worker.js` for the Cloudflare demo. `app/surveys.js` is the single source of truth for survey texts: after editing it run `node app/export-surveys.js` to regenerate `php/surveys.json` (`--check` verifies). Env and details: [`app/README.md`](app/README.md). |
| [`DESIGN.md`](DESIGN.md), [`ds.css`](ds.css), [`design-system.html`](design-system.html) | Design system |
| [`index.html`](index.html) | Product prototype |
| [`to-questionnaire-nukus-endo-feedback.md`](to-questionnaire-nukus-endo-feedback.md) | Requirements questionnaire |
| [`workflows/`](workflows) | Design/review agent workflows |

Quick start:

```sh
cd app && cp .env.example .env && npm install && npm run dev
```

Printed poster PDFs and deploy zips are not in git (`.gitignore`).
