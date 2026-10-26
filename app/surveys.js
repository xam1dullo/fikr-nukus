'use strict';
// Questions copied from the center's 7 Google Forms (2026-10-19). The "F.I.Sh." text field is gone: the QR identifies the person.
// t: scale = 1–5, yn = yes/no(/partly), choice = one of opts, nps = 0–10. bad = the answer that counts as a red flag.
// qq (Karakalpak) strings need a native speaker's review before posters go up.

const { toCyr } = require('./public/translit');

// uz = Uzbek Latin, oz = Uzbek Cyrillic (the gov.uz code), qq = Karakalpak, ru = Russian
const LANGS = ['uz', 'oz', 'qq', 'ru'];
const yn = (id, uz, qq, ru, extra = {}) => ({ id, t: 'yn', opts: ['yes', 'no'], uz, qq, ru, ...extra });
const yn3 = (id, uz, qq, ru) => ({ id, t: 'yn', opts: ['yes', 'partly', 'no'], uz, qq, ru });
const sc = (id, uz, qq, ru) => ({ id, t: 'scale', uz, qq, ru });
const nps = (uz, qq, ru) => ({ id: 'nps', t: 'nps', uz, qq, ru });

const POLITE = ['Muloyimlik va munosabat', 'Sıpayılıq hám múnásibet'];
const SPEED = ['Xizmat koʻrsatish tezligi', 'Xızmet kórsetiw tezligi', 'Скорость обслуживания'];
const BOOKING = ['Qabulga yozilish qulayligi', 'Qabıllawǵa jazılıwdıń qolaylıǵı', 'Удобство записи на приём'];

const SURVEYS = {
  doctor: {
    label: { uz: 'Shifokor', qq: 'Shıpaker', ru: 'Врач' },
    q: [
      sc('attention', 'Shifokorning shikoyatlaringizga eʼtibori', 'Shıpakerdiń shaǵımlarıńızǵa itibarı', 'Внимательность к жалобам'),
      sc('explain', 'Diagnoz va davolashni tushuntirish darajasi', 'Diagnoz hám emlewdi túsindiriw dárejesi', 'Понятность объяснения диагноза и лечения'),
      sc('polite', 'Xushmuomalalik va hurmat', 'Sıpayılıq hám húrmet', 'Вежливость и уважение'),
      yn('time', 'Shifokor sizga yetarli vaqt ajratdimi?', 'Shıpaker sizge jeterli waqıt ajırattı ma?', 'Уделил ли врач достаточно времени?'),
      yn('supplement', 'Shifokor BAD, aniq dori yoki maʼlum dorixonani tavsiya qildimi?', 'Shıpaker BAD, belgili dári yamasa belgili dárixananı usınıs etti me?', 'Рекомендовал ли врач БАДы, конкретный препарат или аптеку?', { bad: 'yes' }),
      nps('Ushbu shifokorni boshqalarga tavsiya qilasizmi?', 'Bul shıpakerdi basqalarǵa usınıs etesiz be?', 'Насколько готовы рекомендовать врача?'),
    ],
  },
  podiatrist: {
    label: { uz: 'Podiatr', qq: 'Podiatr', ru: 'Подиатр' },
    q: [
      sc('polite', ...POLITE, 'Вежливость и отношение'),
      sc('explain', 'Diagnoz va davolash rejasini tushuntirish', 'Diagnoz hám emlew rejesin túsindiriw', 'Объяснение диагноза и плана лечения'),
      sc('accuracy', 'Muolajadagi aniqlik va professionallik', 'Proceduradaǵı anıqlıq hám kásiplik', 'Аккуратность и профессионализм'),
      sc('comfort', 'Muolaja qulayligi', 'Proceduranıń qolaylıǵı', 'Комфорт процедуры'),
      yn('advice', 'Oyoq parvarishi boʻyicha tavsiyalar berildimi?', 'Ayaq kútimi boyınsha usınıslar berildi me?', 'Даны ли рекомендации по уходу за стопами?'),
      nps('Podiatrni tavsiya qilasizmi?', 'Podiatrdı usınıs etesiz be?', 'Насколько готовы рекомендовать подиатра?'),
    ],
  },
  imaging: {
    label: { uz: 'UZI / MRT / Rentgen', qq: 'UZI / MRT / Rentgen', ru: 'УЗИ / МРТ / Рентген' },
    q: [
      { id: 'service', t: 'choice', opts: ['uzi', 'mrt', 'xray'], uz: 'Xizmat turi', qq: 'Xızmet túri', ru: 'Вид исследования' },
      sc('polite', ...POLITE, 'Вежливость и отношение врача'),
      sc('explain', 'Tekshiruv jarayonini tushuntirish', 'Tekseriw barısın túsindiriw', 'Объяснение хода исследования'),
      sc('accuracy', 'Aniqlik va professionallik', 'Anıqlıq hám kásiplik', 'Аккуратность и профессионализм'),
      sc('comfort', 'Jarayon va xona qulayligi', 'Procedura hám bólmeniń qolaylıǵı', 'Комфорт процедуры и помещения'),
      yn('results', 'Natijalarni oʻz vaqtida oldingizmi?', 'Nátiyjelerdi óz waqtında aldıńız ba?', 'Получили ли результаты вовремя?'),
      nps('Ushbu shifokorni tavsiya qilasizmi?', 'Bul shıpakerdi usınıs etesiz be?', 'Насколько готовы рекомендовать врача?'),
    ],
  },
  nurse: {
    label: { uz: 'Qon olish hamshirasi', qq: 'Qan alıw hámshiresi', ru: 'Медсестра (забор крови)' },
    q: [
      sc('polite', 'Hamshiraning muloyimligi va munosabati', 'Hámshireniń sıpayılıǵı hám múnásibeti', 'Вежливость и отношение медсестры'),
      sc('accuracy', 'Qon olishdagi aniqlik va professionallik', 'Qan alıwdaǵı anıqlıq hám kásiplik', 'Аккуратность и профессионализм'),
      sc('clean', 'Tozalik va sterillik', 'Tazalıq hám sterillik', 'Чистота и стерильность'),
      yn3('pain', 'Jarayon ogʻriqsiz yoki kam ogʻriqli boʻldimi?', 'Procedura awırıwsız yamasa az awırıwlı boldı ma?', 'Была ли процедура безболезненной?'),
      yn3('explained', 'Hamshira jarayonni tushuntirib, tavsiya berdimi?', 'Hámshire proceduranı túsindirip, usınıs berdi me?', 'Объяснила ли медсестра процедуру и дала рекомендации?'),
      nps('Ushbu xizmatni tavsiya qilasizmi?', 'Bul xızmetti usınıs etesiz be?', 'Насколько готовы рекомендовать услугу?'),
    ],
  },
  clinic: {
    label: { uz: 'Ambulatoriya poliklinikasi', qq: 'Ambulatoriya poliklinikası', ru: 'Амбулаторная поликлиника' },
    q: [
      sc('booking', ...BOOKING),
      sc('wait', 'Kutish vaqti', 'Kútiw waqtı', 'Время ожидания'),
      sc('registry', 'Registratura va administratorlar ishi', 'Registratura hám administratorlardıń jumısı', 'Работа регистратуры и администраторов'),
      sc('doctors', 'Shifokorlarning eʼtibori va professionalligi', 'Shıpakerlerdiń itibarı hám kásipligi', 'Внимательность и профессионализм врачей'),
      sc('clean', 'Tozalik va qulaylik', 'Tazalıq hám qolaylıq', 'Чистота и комфорт'),
      sc('results', 'Tahlil va tekshiruv natijalarini olish qulayligi', 'Analiz hám tekseriw nátiyjelerin alıwdıń qolaylıǵı', 'Удобство получения результатов'),
      nps('Poliklinikani boshqalarga tavsiya qilasizmi?', 'Poliklinikanı basqalarǵa usınıs etesiz be?', 'Насколько готовы рекомендовать поликлинику?'),
    ],
  },
  cashier: {
    label: { uz: 'Kassa', qq: 'Kassa', ru: 'Касса' },
    q: [
      sc('polite', 'Kassirning muloyimligi va munosabati', 'Kassirdiń sıpayılıǵı hám múnásibeti', 'Вежливость и отношение кассира'),
      sc('speed', ...SPEED),
      sc('accuracy', 'Hisob-kitobning aniqligi', 'Esap-kitaptıń anıqlıǵı', 'Точность расчёта'),
      sc('explain', 'Narx, qaytim va chek boʻyicha tushuntirish', 'Baha, qaytım hám chek boyınsha túsindiriw', 'Объяснение стоимости, сдачи и чека'),
      yn('receipt', 'Kassir sizga kassa chekini berdimi?', 'Kassir sizge kassa chekin berdi me?', 'Выдали ли Вам кассовый чек?', { bad: 'no' }),
      yn('upsell', 'Sizga ortiqcha xizmat yoki mahsulot majburan taklif qilindimi?', 'Sizge artıqsha xızmet yamasa ónim májbúrlep usınıldı ma?', 'Навязывали ли ненужные услуги или товары?', { bad: 'yes' }),
    ],
  },
  registry: {
    label: { uz: 'Registratura', qq: 'Registratura', ru: 'Регистратура' },
    q: [
      sc('polite', 'Xodimlarning muloyimligi va munosabati', 'Xızmetkerlerdiń sıpayılıǵı hám múnásibeti', 'Вежливость и отношение сотрудников'),
      sc('speed', ...SPEED),
      sc('booking', ...BOOKING),
      sc('competence', 'Savollarga malakali javob berilishi', 'Sorawlarǵa bilimli juwap beriliwi', 'Компетентность ответов'),
      yn('upsell', 'Sizga keraksiz xizmat, tahlil yoki boshqa klinika tavsiya qilindimi?', 'Sizge keraksiz xızmet, analiz yamasa basqa klinika usınıldı ma?', 'Предлагали ли ненужные услуги, анализы или другую клинику?', { bad: 'yes' }),
    ],
  },
};

const OPT = {
  yes: { uz: 'Ha', qq: 'Awa', ru: 'Да' }, partly: { uz: 'Qisman', qq: 'Bólekshe', ru: 'Частично' }, no: { uz: 'Yoʻq', qq: 'Yaq', ru: 'Нет' },
  uzi: { uz: 'UZI', qq: 'UZI', ru: 'УЗИ' }, mrt: { uz: 'MRT', qq: 'MRT', ru: 'МРТ' }, xray: { uz: 'Rentgen', qq: 'Rentgen', ru: 'Рентген' },
};

// Uzbek Cyrillic is derived, never hand-written, so it can't drift from the Latin source.
for (const s of Object.values(SURVEYS)) { s.label.oz = toCyr(s.label.uz); for (const q of s.q) q.oz = toCyr(q.uz); }
for (const o of Object.values(OPT)) o.oz = toCyr(o.uz);

// Validates one submission against its template. Returns null on any invalid or missing answer.
function grade(key, raw) {
  const s = Object.hasOwn(SURVEYS, key) && SURVEYS[key];
  if (!s || !raw || typeof raw !== 'object') return null;
  const answers = {};
  let sum = 0, n = 0, flags = 0, nps = null;
  for (const q of s.q) {
    const v = raw[q.id];
    if (q.t === 'scale') { if (!Number.isInteger(v) || v < 1 || v > 5) return null; sum += v; n++; }
    else if (q.t === 'nps') { if (!Number.isInteger(v) || v < 0 || v > 10) return null; nps = v; }
    else if (!q.opts.includes(v)) return null;
    if (q.bad && v === q.bad) flags++;
    answers[q.id] = v;
  }
  return { answers, score: n ? Math.round((sum / n) * 100) / 100 : null, nps, flags };
}

// Readable phone label for the admin. Chrome on Android hides the model in the UA ("Android 10; K"),
// so the page sends the client-hint model; iPhones never expose a model, only the screen size.
function deviceInfo(ua = '', dev = {}) {
  const s = (v, n) => String(v || '').replace(/[^\w .,()\-/+]/g, '').trim().slice(0, n);
  let model = s(dev.model, 60), os = '';
  const android = /Android ([\d.]+)(?:; ([^;)]+))?/.exec(ua), ios = /(iPhone|iPad|iPod).*? OS (\d+)_(\d+)/.exec(ua);
  if (android) { os = 'Android ' + (s(dev.platformVersion, 12) || android[1]); if (!model && android[2] && android[2].trim() !== 'K') model = s(android[2].replace(/ Build\/.*/, ''), 60); }
  else if (ios) { os = `iOS ${ios[2]}.${ios[3]}`; model = model || ios[1]; }
  else if (/Windows/.test(ua)) os = 'Windows';
  else if (/Mac OS X/.test(ua)) os = 'macOS';
  else if (/Linux/.test(ua)) os = 'Linux';
  // ponytail: brands by model-code prefix for the common phones in UZ; unknown codes show as-is
  const brand = [[/^SM-/, 'Samsung'], [/^CPH/, 'Oppo'], [/^RMX/, 'Realme'], [/^V\d{4}/, 'Vivo'], [/^(M\d{4}|2\d{5,})/, 'Xiaomi']].find(([re]) => re.test(model));
  if (brand) model = `${brand[1]} ${model}`;
  const w = Number.isInteger(dev.w) && dev.w < 10000 ? dev.w : 0, h = Number.isInteger(dev.h) && dev.h < 10000 ? dev.h : 0;
  return { model: model || (android ? 'Android' : os ? 'Kompyuter' : 'Nomaʼlum'), os, screen: w && h ? `${w}×${h}` : '' };
}

// Optional callback number, Uzbek only. '' → null (left blank); undefined → invalid (reject, don't drop silently).
function cleanPhone(v) {
  const d = String(v ?? '').replace(/\D/g, '');
  if (!d) return null;
  const n = d.length === 12 && d.startsWith('998') ? d.slice(3) : d;
  return /^\d{9}$/.test(n) ? '+998' + n : undefined;
}

module.exports = { SURVEYS, OPT, LANGS, grade, deviceInfo, cleanPhone };
