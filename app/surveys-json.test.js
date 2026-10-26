'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const { out, json } = require('./export-surveys');

test('php/surveys.json matches surveys.js (run: node export-surveys.js)', () => {
  assert.strictEqual(fs.readFileSync(out, 'utf8'), json);
});
