'use strict';
// Fikr — patient feedback server. Plain node:http + mysql2; cPanel "Setup Node.js App" (Passenger) runs this file.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { SURVEYS, OPT, LANGS, grade, deviceInfo, cleanPhone } = require('./surveys');

const env = process.env;

if (process.argv[2] === 'hash') {
  // usage: node app.js hash   → type the password, paste the printed line into ADMIN_HASH
  process.stdout.write('Parol: ');
  process.stdin.once('data', (d) => {
    const pass = String(d).trim();
    if (pass.length < 10) { console.error('Kamida 10 belgi.'); process.exit(1); }
    const salt = crypto.randomBytes(16).toString('hex');
    console.log(`\nADMIN_HASH=scrypt:${salt}:${crypto.scryptSync(pass, salt, 64).toString('hex')}`);
    process.exit(0);
  });
  return;
}

for (const k of ['DB_USER', 'DB_NAME', 'ADMIN_USER', 'ADMIN_HASH', 'SESSION_SECRET']) {
  if (!env[k]) { console.error(`Missing env ${k}`); process.exit(1); }
}

const mysql = require('mysql2/promise');
const db = mysql.createPool({
  host: env.DB_HOST || 'localhost', port: +(env.DB_PORT || 3306), user: env.DB_USER, password: env.DB_PASS, database: env.DB_NAME,
  charset: 'utf8mb4', dateStrings: true, connectionLimit: 5,
});

const PUB = path.join(__dirname, 'public');
const VERSION = String(Date.now()); // cache-buster for /a/* assets, changes on every restart
const SEC = { 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'same-origin', 'X-Frame-Options': 'DENY' };
const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
const MIME = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.svg': 'image/svg+xml', '.png': 'image/png', '.html': 'text/html; charset=utf-8' };

// Asia/Tashkent is UTC+5 with no DST, so a fixed offset is exact.
const nowLocal = () => new Date(Date.now() + 5 * 3600e3).toISOString().slice(0, 19).replace('T', ' ');
const curMonth = () => nowLocal().slice(0, 7);
const shiftMonth = (m, d) => { const [y, mo] = m.split('-').map(Number); return new Date(Date.UTC(y, mo - 1 + d, 1)).toISOString().slice(0, 7); };
const range = (m) => [`${m}-01 00:00:00`, `${shiftMonth(m, 1)}-01 00:00:00`];
const monthOf = (u) => (/^\d{4}-\d{2}$/.test(u.searchParams.get('month') || '') ? u.searchParams.get('month') : curMonth());

function send(res, code, body, headers = {}) {
  const json = !(typeof body === 'string' || Buffer.isBuffer(body));
  res.writeHead(code, { ...SEC, 'Content-Type': json ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8', 'Cache-Control': 'no-store', ...headers });
  res.end(json ? JSON.stringify(body) : body);
}
const page = (res, html, code = 200, headers = {}) => send(res, code, html, { 'Content-Type': MIME['.html'], 'Content-Security-Policy': CSP, ...headers });

function readJson(req) {
  return new Promise((resolve, reject) => {
    if (!String(req.headers['content-type']).startsWith('application/json')) return reject(Object.assign(new Error('type'), { code: 415 }));
    let size = 0; const chunks = [];
    req.on('data', (c) => { size += c.length; if (size > 32 * 1024) { reject(Object.assign(new Error('big'), { code: 413 })); req.destroy(); } else chunks.push(c); });
    req.on('end', () => { try { resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')) || {}); } catch { reject(Object.assign(new Error('json'), { code: 400 })); } });
    req.on('error', reject);
  });
}
const cookies = (req) => Object.fromEntries(String(req.headers.cookie || '').split(';').map((c) => c.trim().split('=')).filter((p) => p[0]).map(([k, ...v]) => [k, v.join('=')]));
const ipOf = (req) => String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.socket.remoteAddress || '';
const secure = env.COOKIE_INSECURE ? '' : '; Secure';

/* ---------- rate limits (in memory; one process on cPanel is enough) ---------- */
const buckets = new Map();
function limited(key, max, windowMs) {
  const now = Date.now(), b = buckets.get(key);
  if (!b || b.reset < now) { buckets.set(key, { n: 1, reset: now + windowMs }); return false; }
  return ++b.n > max;
}
setInterval(() => { const now = Date.now(); for (const [k, b] of buckets) if (b.reset < now) buckets.delete(k); }, 600e3).unref();

/* ---------- admin session: stateless HMAC cookie ---------- */
const sign = (v) => crypto.createHmac('sha256', env.SESSION_SECRET).update(v).digest('base64url');
const safeEq = (a, b) => { const x = Buffer.from(String(a)), y = Buffer.from(String(b)); return x.length === y.length && crypto.timingSafeEqual(x, y); };
function authed(req) {
  const [exp, sig] = String(cookies(req).s || '').split('.');
  return !!sig && safeEq(sig, sign(exp)) && +exp > Date.now();
}
function checkPassword(user, pass) {
  const [, salt, hash] = env.ADMIN_HASH.split(':');
  const got = crypto.scryptSync(String(pass || ''), salt || '', 64).toString('hex');
  const okUser = safeEq(String(user || '').trim().toLowerCase(), env.ADMIN_USER.toLowerCase());
  return safeEq(got, hash || '') && okUser;
}

/* ---------- schema ---------- */
async function migrate() {
  await db.query(`CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(12) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, position VARCHAR(500) NOT NULL DEFAULT '',
    dept VARCHAR(160) NOT NULL DEFAULT '', template VARCHAR(32) NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT 'person', active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4`);
  await db.query(`CREATE TABLE IF NOT EXISTS response (
    id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT NOT NULL, template VARCHAR(32) NOT NULL, lang CHAR(2) NOT NULL, answers TEXT NOT NULL,
    score DECIMAL(3,2) NULL, nps TINYINT NULL, flags TINYINT NOT NULL DEFAULT 0, comment TEXT NULL, phone VARCHAR(13) NULL, device VARCHAR(32) NOT NULL,
    dev_model VARCHAR(60) NOT NULL DEFAULT '', dev_os VARCHAR(20) NOT NULL DEFAULT '', dev_screen VARCHAR(12) NOT NULL DEFAULT '', day DATE NOT NULL,
    created_at DATETIME NOT NULL, UNIQUE KEY one_per_day (staff_id, device, day), KEY by_staff (staff_id, created_at), KEY by_time (created_at)) DEFAULT CHARSET=utf8mb4`);
  try { await db.query('ALTER TABLE response ADD COLUMN phone VARCHAR(13) NULL AFTER comment'); } catch (e) { if (e.code !== 'ER_DUP_FIELDNAME') throw e; }
  const [[{ n }]] = await db.query('SELECT COUNT(*) n FROM staff');
  if (!n) {
    const seed = JSON.parse(fs.readFileSync(path.join(__dirname, 'seed/staff.json'), 'utf8'));
    for (const s of seed) await insertStaff(s);
    console.log(`Seeded ${seed.length} staff`);
  }
}
const CODE_ABC = 'abcdefghjkmnpqrstuvwxyz23456789';
const newCode = () => Array.from(crypto.randomBytes(7), (b) => CODE_ABC[b % CODE_ABC.length]).join('');
async function insertStaff(s) {
  for (;;) {
    try {
      const [r] = await db.query('INSERT INTO staff (code,name,position,dept,template,kind,active,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [newCode(), s.name, s.position || '', s.dept || '', s.template, s.kind === 'point' ? 'point' : 'person', s.active ? 1 : 0, nowLocal()]);
      return r.insertId;
    } catch (e) { if (e.code !== 'ER_DUP_ENTRY') throw e; }
  }
}
function cleanStaff(b) {
  const name = String(b.name || '').trim().slice(0, 160);
  if (!name || !Object.hasOwn(SURVEYS, b.template)) return null;
  return { name, position: String(b.position || '').trim().slice(0, 500), dept: String(b.dept || '').trim().slice(0, 160), template: b.template, kind: b.kind === 'point' ? 'point' : 'person', active: b.active ? 1 : 0 };
}

/* ---------- aggregates ---------- */
const AGG = `COUNT(r.id) n, SUM(r.score) ssum, COUNT(r.score) sn, SUM(r.nps >= 9) pro, SUM(r.nps <= 6) det, COUNT(r.nps) npsn,
  COALESCE(SUM(r.flags > 0),0) flags, COUNT(r.comment) comments`;
// half-up to 2 decimals; the epsilon stops 3.165 (stored as 3.16499…) from rounding down
const r2 = (x) => Math.round(+x * 100 + 1e-6) / 100;
function finish(r) {
  return {
    n: +r.n || 0, avg: +r.sn ? r2(r.ssum / r.sn) : null,
    nps: +r.npsn ? Math.round(((r.pro - r.det) / r.npsn) * 100) : null, flags: +r.flags || 0, comments: +r.comments || 0,
  };
}
async function totals([from, to]) {
  const [[r]] = await db.query(`SELECT ${AGG} FROM response r WHERE r.created_at >= ? AND r.created_at < ?`, [from, to]);
  return finish(r);
}
// For the running month compare like with like: 1–20 Oct against 1–20 Sep, not against all of September.
function prevRange(m) {
  const pm = shiftMonth(m, -1), [from, fullEnd] = range(pm);
  if (m !== curMonth()) return { range: [from, fullEnd], partial: false };
  const now = nowLocal(), day = +now.slice(8, 10), last = new Date(Date.UTC(+pm.slice(0, 4), +pm.slice(5), 0)).getUTCDate();
  if (day > last) return { range: [from, fullEnd], partial: false };
  return { range: [from, `${pm}-${now.slice(8)}`], partial: true, until: `${pm}-${now.slice(8, 10)}` };
}
const parseResp = (r) => ({ ...r, answers: JSON.parse(r.answers), score: r.score === null ? null : +r.score });

/* ---------- pages ---------- */
const tpl = (f) => fs.readFileSync(path.join(PUB, f), 'utf8').replaceAll('{{V}}', VERSION);
const SURVEY_HTML = tpl('s.html'), ADMIN_HTML = tpl('admin.html');
const jsonForScript = (o) => JSON.stringify(o).replace(/</g, '\\u003c');
const NOT_FOUND = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Topilmadi</title><body style="font:16px system-ui;padding:40px;text-align:center"><h2>Bu QR kod hozir ishlamaydi</h2><p>Iltimos, registraturaga murojaat qiling.</p><p>Бу QR код ҳозир ишламайди. Илтимос, регистратурага мурожаат қилинг.</p><p>Bul QR kod házir islemeydi.</p><p>Этот QR-код сейчас не работает. Обратитесь в регистратуру.</p>';

async function patientPage(req, res, code) {
  const [[s]] = await db.query('SELECT name,dept,template,kind FROM staff WHERE code = ? AND active = 1', [code]);
  if (!s) return page(res, NOT_FOUND, 404);
  const data = { code, name: s.name, dept: s.dept, kind: s.kind, survey: SURVEYS[s.template], opt: OPT };
  const headers = {};
  if (!cookies(req).d) headers['Set-Cookie'] = `d=${crypto.randomBytes(12).toString('base64url')}; Path=/; Max-Age=31536000; HttpOnly; SameSite=Lax${secure}`;
  page(res, SURVEY_HTML.replace('/*DATA*/null', jsonForScript(data)), 200, headers);
}

async function submit(req, res, code) {
  const ip = ipOf(req);
  if (limited('r:' + ip, 40, 3600e3)) return send(res, 429, { error: 'rate' });
  const b = await readJson(req);
  const [[s]] = await db.query('SELECT id,template FROM staff WHERE code = ? AND active = 1', [code]);
  if (!s) return send(res, 404, { error: 'staff' });
  const g = grade(s.template, b.a);
  if (!g) return send(res, 400, { error: 'answers' });
  const lang = LANGS.includes(b.lang) ? b.lang : 'uz';
  const dev = deviceInfo(String(req.headers['user-agent'] || ''), b.dev && typeof b.dev === 'object' ? b.dev : {});
  const comment = String(b.c || '').trim().slice(0, 1000) || null;
  const phone = cleanPhone(b.p);
  if (phone === undefined) return send(res, 422, { error: 'phone' });
  // cookie blocked or cleared: fall back to ip+UA so one phone still can't flood one person in a day
  const device = cookies(req).d || 'h' + crypto.createHash('sha256').update(ip + req.headers['user-agent']).digest('base64url').slice(0, 20);
  const at = nowLocal();
  try {
    await db.query('INSERT INTO response (staff_id,template,lang,answers,score,nps,flags,comment,phone,device,dev_model,dev_os,dev_screen,day,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [s.id, s.template, lang, JSON.stringify(g.answers), g.score, g.nps, g.flags, comment, phone, String(device).slice(0, 32), dev.model, dev.os, dev.screen, at.slice(0, 10), at]);
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') return send(res, 409, { error: 'dup' });
    throw e;
  }
  send(res, 201, { ok: true });
}

/* ---------- admin api ---------- */
const cell = (v) => {
  let s = v === null || v === undefined ? '' : String(v);
  // Excel in uz/ru reads "4.5" as a date (4 May); a decimal comma keeps it a number, and "-12" stays a number, not a formula
  if (/^-?\d+(\.\d+)?$/.test(s)) return s.replace('.', ',');
  if (/^[=+\-@]/.test(s)) s = "'" + s; // block formula injection from patient comments
  return /[";\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
};
// Excel in ru/uz locales splits on ';' and needs the BOM to read UTF-8
// '+998…' would read as a formula in Excel; the column header carries the +998
const phoneCell = (p) => p && p.slice(4).replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
const csv = (rows) => '﻿' + rows.map((r) => r.map(cell).join(';')).join('\r\n');
const csvHeaders = (name) => ({ 'Content-Type': 'text/csv; charset=utf-8', 'Content-Disposition': `attachment; filename="${name}"` });

async function adminApi(req, res, u, parts) {
  const m = monthOf(u);
  const [, , what, id] = parts; // /api/<what>/<id>

  if (what === 'me' && req.method === 'GET') {
    return send(res, 200, { name: env.ADMIN_NAME || 'Bosh shifokor', today: nowLocal().slice(0, 10), base: env.PUBLIC_URL || `https://${req.headers.host}`, month: curMonth(), templates: Object.fromEntries(Object.entries(SURVEYS).map(([k, v]) => [k, v.label])) });
  }

  if (what === 'stats' && req.method === 'GET') {
    const [rows] = await db.query(`SELECT s.id,s.code,s.name,s.position,s.dept,s.template,s.kind,s.active, ${AGG}
      FROM staff s LEFT JOIN response r ON r.staff_id = s.id AND r.created_at >= ? AND r.created_at < ?
      GROUP BY s.id ORDER BY s.name`, range(m));
    const [daily] = await db.query(`SELECT DATE_FORMAT(created_at,'%Y-%m-%d') d, COUNT(*) n, AVG(score) avg FROM response
      WHERE created_at >= ? AND created_at < ? GROUP BY d ORDER BY d`, range(m));
    const [byTpl] = await db.query(`SELECT r.template k, ${AGG} FROM response r WHERE r.created_at >= ? AND r.created_at < ? GROUP BY r.template`, range(m));
    const [byDept] = await db.query(`SELECT s.dept k, ${AGG} FROM response r JOIN staff s ON s.id = r.staff_id
      WHERE r.created_at >= ? AND r.created_at < ? AND s.dept <> '' GROUP BY s.dept`, range(m));
    const pr = prevRange(m);
    return send(res, 200, {
      month: m, totals: await totals(range(m)), prev: { ...(await totals(pr.range)), partial: pr.partial, until: pr.until || null },
      byTemplate: byTpl.map((x) => ({ k: x.k, label: SURVEYS[x.k]?.label.uz || x.k, ...finish(x) })),
      byDept: byDept.map((x) => ({ k: x.k, label: x.k, ...finish(x) })),
      daily: daily.map((x) => ({ d: x.d, n: +x.n, avg: x.avg === null ? null : r2(x.avg) })),
      staff: rows.map((r) => ({ id: r.id, code: r.code, name: r.name, position: r.position, dept: r.dept, template: r.template, kind: r.kind, active: !!r.active, ...finish(r) })),
    });
  }

  if (what === 'staff' && id && req.method === 'GET') {
    const [[s]] = await db.query('SELECT id,code,name,position,dept,template,kind,active FROM staff WHERE id = ?', [+id]);
    if (!s) return send(res, 404, { error: 'staff' });
    const [resp] = await db.query('SELECT r.id,r.template,r.lang,r.answers,r.score,r.nps,r.flags,r.comment,r.phone,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? ORDER BY r.created_at DESC', [s.id, ...range(m)]);
    const [trend] = await db.query(`SELECT DATE_FORMAT(r.created_at,'%Y-%m') m, ${AGG} FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? GROUP BY m ORDER BY m`,
      [s.id, `${shiftMonth(m, -5)}-01 00:00:00`, range(m)[1]]);
    return send(res, 200, { staff: s, month: m, surveys: SURVEYS, opt: OPT, responses: resp.map(parseResp), trend: trend.map((t) => ({ m: t.m, ...finish(t) })) });
  }

  if (what === 'staff' && req.method === 'POST' && !id) {
    const s = cleanStaff(await readJson(req));
    if (!s) return send(res, 400, { error: 'invalid' });
    return send(res, 201, { id: await insertStaff(s) });
  }
  if (what === 'staff' && id && req.method === 'PUT') {
    const s = cleanStaff(await readJson(req));
    if (!s) return send(res, 400, { error: 'invalid' });
    await db.query('UPDATE staff SET name=?,position=?,dept=?,template=?,kind=?,active=? WHERE id=?', [s.name, s.position, s.dept, s.template, s.kind, s.active, +id]);
    return send(res, 200, { ok: true });
  }

  if (what === 'comments' && req.method === 'GET') {
    const [rows] = await db.query(`SELECT r.id,r.score,r.nps,r.flags,r.comment,r.phone,r.answers,r.template,r.lang,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id,s.id staff_id,s.name,s.dept
      FROM response r JOIN staff s ON s.id = r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL)
      ORDER BY r.created_at DESC LIMIT 1000`, range(m));
    return send(res, 200, { month: m, surveys: SURVEYS, opt: OPT, items: rows.map(parseResp) });
  }

  if (what === 'export' && req.method === 'GET') {
    if (u.searchParams.get('type') === 'comments') {
      const [rows] = await db.query(`SELECT r.created_at,s.name,s.dept,r.score,r.nps,r.flags,r.comment,r.phone,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r JOIN staff s ON s.id=r.staff_id
        WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL) ORDER BY r.created_at`, range(m));
      return send(res, 200, csv([['Sana', 'Xodim', 'Boʻlim', 'Baho', 'NPS', 'Ogohlantirish', 'Izoh', 'Telefon (+998)', 'Qurilma', 'OS', 'Ekran', 'Qurilma ID'],
        ...rows.map((r) => [r.created_at, r.name, r.dept, r.score, r.nps, r.flags, r.comment, phoneCell(r.phone), r.dev_model, r.dev_os, r.dev_screen, r.dev_id])]), csvHeaders(`izohlar-${m}.csv`));
    }
    const [rows] = await db.query(`SELECT s.name,s.position,s.dept,s.template,s.active, ${AGG} FROM staff s
      LEFT JOIN response r ON r.staff_id=s.id AND r.created_at >= ? AND r.created_at < ? GROUP BY s.id ORDER BY s.name`, range(m));
    return send(res, 200, csv([['Xodim', 'Lavozim', 'Boʻlim', 'Soʻrovnoma', 'Javoblar', 'Oʻrtacha baho (1–5)', 'NPS', 'Ogohlantirishlar', 'Izohlar'],
      ...rows.filter((r) => r.active || +r.n).map((r) => { const f = finish(r); return [r.name, r.position, r.dept, SURVEYS[r.template]?.label.uz, f.n, f.avg, f.nps, f.flags, f.comments]; })]),
    csvHeaders(`hisobot-${m}.csv`));
  }

  send(res, 404, { error: 'not found' });
}

/* ---------- router ---------- */
async function handle(req, res) {
  const u = new URL(req.url, 'http://x');
  const parts = u.pathname.split('/');

  if (u.pathname.startsWith('/a/') && req.method === 'GET') {
    const file = path.join(PUB, path.normalize(decodeURIComponent(u.pathname.slice(3))));
    if (!file.startsWith(PUB + path.sep) || file.endsWith('.html')) return send(res, 404, 'not found');
    return fs.readFile(file, (err, buf) => (err ? send(res, 404, 'not found')
      : send(res, 200, buf, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'public, max-age=2592000, immutable' })));
  }
  if (parts[1] === 's' && parts[2] && req.method === 'GET') return patientPage(req, res, parts[2]);
  if (parts[1] === 'api' && parts[2] === 'r' && parts[3] && req.method === 'POST') return submit(req, res, parts[3]);
  if (u.pathname === '/admin' && req.method === 'GET') return page(res, ADMIN_HTML);
  if (u.pathname === '/') { res.writeHead(302, { Location: '/admin' }); return res.end(); }

  if (u.pathname === '/api/login' && req.method === 'POST') {
    const ip = ipOf(req);
    if (limited('l:' + ip, 8, 900e3)) return send(res, 429, { error: 'rate' });
    const b = await readJson(req);
    if (!checkPassword(b.user, b.pass)) return send(res, 401, { error: 'login' });
    buckets.delete('l:' + ip);
    const exp = String(Date.now() + 12 * 3600e3);
    return send(res, 200, { ok: true }, { 'Set-Cookie': `s=${exp}.${sign(exp)}; Path=/; Max-Age=43200; HttpOnly; SameSite=Strict${secure}` });
  }
  if (u.pathname === '/api/logout' && req.method === 'POST') return send(res, 200, { ok: true }, { 'Set-Cookie': `s=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict${secure}` });

  if (parts[1] === 'api') {
    if (!authed(req)) return send(res, 401, { error: 'auth' });
    // admin writes need a JSON body (readJson enforces it) and SameSite=Strict blocks cross-site cookies → no CSRF token needed
    return adminApi(req, res, u, parts);
  }
  send(res, 404, 'not found');
}

http.createServer((req, res) => {
  handle(req, res).catch((e) => {
    if (e.code >= 400 && e.code < 500) return send(res, e.code, { error: e.message });
    console.error(new Date().toISOString(), req.method, req.url, e);
    if (!res.headersSent) send(res, 500, { error: 'server' });
  });
}).listen(+(env.PORT || 3000), () => console.log('Fikr listening', env.PORT || 3000));

migrate().catch((e) => { console.error('DB init failed', e); process.exit(1); });
