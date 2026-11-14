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
test('Karakalpak Latin to Cyrillic', () => {
  const { toCyrQQ } = require('./public/translit');
  const cases = {
    'Shıpakerdiń shaǵımlarıńızǵa itibarı': 'Шыпакердиң шағымларыңызға итибары', 'Nókis orayı': 'Нөкис орайы',
    'Diagnoz hám emlewdi túsindiriw dárejesi': 'Диагноз ҳәм емлеўди түсиндириў дәрежеси', 'Procedura': 'Процедура', 'Awa': 'Аўа', 'Ájayıp': 'Әжайып',
    'QR kodtı skanerleń': 'QR кодты сканерлең', 'BAD': 'БАД',
  };
  for (const [lat, cyr] of Object.entries(cases)) assert.strictEqual(toCyrQQ(lat), cyr, lat);
});
