'use strict';
// Writes ../php/surveys.json from surveys.js so the PHP server shares one source of truth.
// usage: node export-surveys.js          → write
//        node export-surveys.js --check  → exit 1 if php/surveys.json is stale
const fs = require('node:fs');
const path = require('node:path');
const { SURVEYS, OPT, LANGS } = require('./surveys');

const out = path.join(__dirname, '..', 'php', 'surveys.json');
const json = JSON.stringify({ SURVEYS, OPT, LANGS }, null, 1) + '\n';
if (process.argv[2] === '--check') {
  const cur = fs.existsSync(out) ? fs.readFileSync(out, 'utf8') : '';
  if (cur !== json) { console.error('php/surveys.json is stale: run node app/export-surveys.js'); process.exit(1); }
  console.log('php/surveys.json is up to date');
} else {
  fs.writeFileSync(out, json);
  console.log('wrote', out);
}
