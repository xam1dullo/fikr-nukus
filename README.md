# Fikr — patient feedback for the Nukus endocrinology center

Every staff member gets a QR poster. Patients scan it on their phone, answer a short survey (uz Cyrillic / uz Latin / Karakalpak / ru), and the chief doctor reviews the results at `/admin`.

| Path | What |
|---|---|
| [`app/`](app) | The product: Node server (`app.js`, MySQL) for cPanel production, `worker.js` for the Cloudflare demo. **Setup, env and deploy: [`app/README.md`](app/README.md).** |
| [`DESIGN.md`](DESIGN.md), [`ds.css`](ds.css), [`design-system.html`](design-system.html) | Design system |
| [`index.html`](index.html) | Product prototype |
| [`to-questionnaire-nukus-endo-feedback.md`](to-questionnaire-nukus-endo-feedback.md) | Requirements questionnaire |
| [`workflows/`](workflows) | Design/review agent workflows |

Quick start:

```sh
cd app && cp .env.example .env && npm install && npm run dev
```

Printed poster PDFs and deploy zips are not in git (`.gitignore`).
