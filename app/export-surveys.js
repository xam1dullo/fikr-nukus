'use strict';
// Writes ../php/surveys.json so the PHP server uses the same surveys as this one. Run after editing surveys.js.
const fs = require('node:fs');
const path = require('node:path');
const { SURVEYS, OPT, LANGS } = require('./surveys');

const out = path.join(__dirname, '../php/surveys.json');
const json = JSON.stringify({ SURVEYS, OPT, LANGS }, null, 1) + '\n';
if (require.main === module) { fs.writeFileSync(out, json); console.log('wrote', out); }
module.exports = { out, json };
