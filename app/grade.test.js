'use strict';
const test = require('node:test');
const assert = require('node:assert');
const { SURVEYS, OPT, LANGS, grade, deviceInfo, cleanPhone } = require('./surveys');

const full = { attention: 5, explain: 4, polite: 3, time: 'yes', supplement: 'yes', nps: 9 };

test('doctor answers: score is the 1–5 mean, nps kept, supplement=yes is a flag', () => {
  assert.deepStrictEqual(grade('doctor', full), { answers: full, score: 4, nps: 9, flags: 1 });
});
test('missing, out-of-range or foreign answers are rejected', () => {
  assert.strictEqual(grade('doctor', { ...full, nps: undefined }), null);
  assert.strictEqual(grade('doctor', { ...full, polite: 6 }), null);
  assert.strictEqual(grade('doctor', { ...full, time: 'maybe' }), null);
  assert.strictEqual(grade('constructor', full), null);
  assert.strictEqual(grade('doctor', null), null);
});
test('extra keys are dropped, cashier has no nps and two flag rules', () => {
  const a = { polite: 5, speed: 5, accuracy: 5, explain: 5, receipt: 'no', upsell: 'yes', junk: 1 };
  assert.deepStrictEqual(grade('cashier', a), { answers: { polite: 5, speed: 5, accuracy: 5, explain: 5, receipt: 'no', upsell: 'yes' }, score: 5, nps: null, flags: 2 });
});
test('every template has unique question ids and text in every language', () => {
  for (const [k, s] of Object.entries(SURVEYS)) {
    assert.strictEqual(new Set(s.q.map((q) => q.id)).size, s.q.length, k);
    for (const l of LANGS) {
      assert.ok(s.label[l], `${k}.label.${l}`);
      for (const q of s.q) { assert.ok(q[l], `${k}.${q.id}.${l}`); for (const o of q.opts || []) assert.ok(OPT[o][l], `${o}.${l}`); }
    }
  }
});
test('device label: client-hint model wins over reduced UA, iPhone keeps screen size', () => {
  const chrome = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Mobile Safari/537.36';
  assert.deepStrictEqual(deviceInfo(chrome, { model: 'SM-A515F', platformVersion: '13.0.0', w: 412, h: 915 }), { model: 'Samsung SM-A515F', os: 'Android 13.0.0', screen: '412×915' });
  assert.deepStrictEqual(deviceInfo(chrome, {}), { model: 'Android', os: 'Android 10', screen: '' });
  const old = 'Mozilla/5.0 (Linux; Android 9; Redmi Note 8 Build/PKQ1) AppleWebKit/537.36';
  assert.strictEqual(deviceInfo(old, {}).model, 'Redmi Note 8');
  const ios = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15';
  assert.deepStrictEqual(deviceInfo(ios, { w: 390, h: 844, model: '<script>' }), { model: 'script', os: 'iOS 17.5', screen: '390×844' });
});

test('phone: blank is null, 9 digits or +998 form normalise, anything else is invalid', () => {
  assert.strictEqual(cleanPhone(''), null);
  assert.strictEqual(cleanPhone(undefined), null);
  assert.strictEqual(cleanPhone('90 123 45 67'), '+998901234567');
  assert.strictEqual(cleanPhone('+998 (90) 123-45-67'), '+998901234567');
  assert.strictEqual(cleanPhone('90 123 45'), undefined);
  assert.strictEqual(cleanPhone('+7 912 345 67 89'), undefined);
});
