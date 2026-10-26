'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const I18N = require('./public/i18n');

// every UI string the admin page asks for must exist in Russian and Karakalpak, or it would silently show Uzbek
test('admin strings: every t() / data-t key has ru and qq', () => {
  const html = fs.readFileSync(__dirname + '/public/admin.html', 'utf8');
  const keys = new Set([
    ...[...html.matchAll(/\bt\('((?:[^'\\]|\\.)*)'/g)].map((m) => m[1]),
    ...[...html.matchAll(/data-t(?:-aria-label)?="([^"]+)"/g)].map((m) => m[1]),
    ...[...html.matchAll(/\['\w+', '([^']+)'\]/g)].map((m) => m[1]),
    ...[...html.matchAll(/t\([^)]*\? '([^']+)' : '([^']+)'\)/g)].flatMap((m) => [m[1], m[2]]),
  ]);
  const missing = [...keys].filter((k) => !I18N.D[k] || !I18N.D[k][0] || !I18N.D[k][1]);
  assert.deepStrictEqual(missing, []);
});
test('placeholders survive translation', () => {
  for (const [k, [ru, qq]] of Object.entries(I18N.D)) {
    const want = (k.match(/\{\w+\}/g) || []).sort().join();
    assert.strictEqual((ru.match(/\{\w+\}/g) || []).sort().join(), want, 'ru: ' + k);
    assert.strictEqual((qq.match(/\{\w+\}/g) || []).sort().join(), want, 'qq: ' + k);
  }
});
test('dates read naturally per language', () => {
  assert.strictEqual(I18N.date('ru', 19, 10), '19 октября');
  assert.strictEqual(I18N.date('uz', 19, 10), '19-oktabr');
  assert.strictEqual(I18N.t('ru', '{n} ta javob', { n: 5 }), 'ответов: 5');
});
