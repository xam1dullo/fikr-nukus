'use strict';
const test = require('node:test');
const assert = require('node:assert');
const { toCyr } = require('./public/translit');

test('Uzbek Latin to Cyrillic: digraphs, apostrophes, word-initial e, case', () => {
  const cases = {
    'Yoʻq': 'Йўқ', "o'z": 'ўз', 'Gʻoyalar': 'Ғоялар', 'eʼtibori': 'эътибори', 'Shifokor': 'Шифокор',
    'SHIFOKOR ISHINI BAHOLANG!': 'ШИФОКОР ИШИНИ БАҲОЛАНГ!', 'yetarli': 'етарли', 'Yangi xodim': 'Янги ходим',
  };
  for (const [lat, cyr] of Object.entries(cases)) assert.strictEqual(toCyr(lat), cyr, lat);
});
test('brand words and abbreviations stay Latin, suffixes still convert', () => {
  assert.strictEqual(toCyr('QR kodni skanerlang'), 'QR кодни сканерланг');
  assert.strictEqual(toCyr('Excelga yuklab olish'), 'Excelга юклаб олиш');
  assert.strictEqual(toCyr('Tavsiya indeksi (NPS)'), 'Тавсия индекси (NPS)');
});
