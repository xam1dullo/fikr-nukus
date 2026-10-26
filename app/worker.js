// Cloudflare Workers + D1 build of the same server as app.js (demo hosting).
// Production for the center stays on the Uzbek cPanel host (ZRU-547); this copy is for showing the system.
import surveys from './surveys.js';
const { SURVEYS, OPT, LANGS, grade, deviceInfo, cleanPhone } = surveys;

const nowLocal = () => new Date(Date.now() + 5 * 3600e3).toISOString().slice(0, 19).replace('T', ' '); // Asia/Tashkent, no DST
const curMonth = () => nowLocal().slice(0, 7);
const shiftMonth = (m, d) => { const [y, mo] = m.split('-').map(Number); return new Date(Date.UTC(y, mo - 1 + d, 1)).toISOString().slice(0, 7); };
const range = (m) => [`${m}-01 00:00:00`, `${shiftMonth(m, 1)}-01 00:00:00`];
const r2 = (x) => Math.round(+x * 100 + 1e-6) / 100;
const SEC = { 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'same-origin', 'X-Frame-Options': 'DENY' };
const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
const enc = new TextEncoder();
const b64url = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const hex = (buf) => [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');

const json = (body, status = 200, headers = {}) => new Response(JSON.stringify(body), { status, headers: { ...SEC, 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', ...headers } });
const html = (body, status = 200, headers = {}) => new Response(body, { status, headers: { ...SEC, 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'Content-Security-Policy': CSP, ...headers } });
const cookies = (req) => Object.fromEntries((req.headers.get('cookie') || '').split(';').map((c) => c.trim().split('=')).filter((p) => p[0]).map(([k, ...v]) => [k, v.join('=')]));
const fail = (status, error) => Object.assign(new Error(error), { status });

/* rate limits per isolate: enough for a demo, the real server keeps its own */
const buckets = new Map();
function limited(key, max, ms) {
  const now = Date.now(), b = buckets.get(key);
  if (!b || b.reset < now) { buckets.set(key, { n: 1, reset: now + ms }); return false; }
  return ++b.n > max;
}

async function hmac(secret, v) {
  const k = await crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  return b64url(await crypto.subtle.sign('HMAC', k, enc.encode(v)));
}
function safeEq(a, b) { a = String(a); b = String(b); let d = a.length ^ b.length; for (let i = 0; i < Math.max(a.length, b.length); i++) d |= (a.charCodeAt(i) || 0) ^ (b.charCodeAt(i) || 0); return d === 0; }
// ADMIN_HASH = "pbkdf2:<iterations>:<salt hex>:<hash hex>" (Workers have no scrypt)
async function checkPassword(env, user, pass) {
  const [, it, salt, want] = String(env.ADMIN_HASH).split(':');
  const key = await crypto.subtle.importKey('raw', enc.encode(String(pass || '')), 'PBKDF2', false, ['deriveBits']);
  const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: enc.encode(salt), iterations: +it }, key, 256);
  return safeEq(hex(bits), want) && safeEq(String(user || '').trim().toLowerCase(), env.ADMIN_USER.toLowerCase());
}
async function authed(req, env) {
  const [exp, sig] = String(cookies(req).s || '').split('.');
  return !!sig && safeEq(sig, await hmac(env.SESSION_SECRET, exp)) && +exp > Date.now();
}

const AGG = `COUNT(r.id) n, SUM(r.score) ssum, COUNT(r.score) sn, SUM(r.nps >= 9) pro, SUM(r.nps <= 6) det, COUNT(r.nps) npsn,
  COALESCE(SUM(r.flags > 0),0) flags, COUNT(r.comment) comments`;
const finish = (r) => ({ n: +r.n || 0, avg: +r.sn ? r2(r.ssum / r.sn) : null, nps: +r.npsn ? Math.round(((r.pro - r.det) / r.npsn) * 100) : null, flags: +r.flags || 0, comments: +r.comments || 0 });
const all = async (db, sql, ...p) => (await db.prepare(sql).bind(...p).all()).results;
const one = async (db, sql, ...p) => db.prepare(sql).bind(...p).first();
const totals = async (db, [a, b]) => finish(await one(db, `SELECT ${AGG} FROM response r WHERE r.created_at >= ? AND r.created_at < ?`, a, b));
function prevRange(m) {
  const pm = shiftMonth(m, -1), [from, fullEnd] = range(pm);
  if (m !== curMonth()) return { range: [from, fullEnd], partial: false };
  const now = nowLocal(), day = +now.slice(8, 10), last = new Date(Date.UTC(+pm.slice(0, 4), +pm.slice(5), 0)).getUTCDate();
  if (day > last) return { range: [from, fullEnd], partial: false };
  return { range: [from, `${pm}-${now.slice(8)}`], partial: true, until: `${pm}-${now.slice(8, 10)}` };
}
const parseResp = (r) => ({ ...r, answers: JSON.parse(r.answers), score: r.score === null ? null : +r.score });
const RESP_COLS = 'r.id,r.template,r.lang,r.answers,r.score,r.nps,r.flags,r.comment,r.phone,r.created_at,r.dev_model,r.dev_os,r.dev_screen,r.dev_id';
const CODE_ABC = 'abcdefghjkmnpqrstuvwxyz23456789';
const newCode = () => Array.from(crypto.getRandomValues(new Uint8Array(7)), (b) => CODE_ABC[b % CODE_ABC.length]).join('');
function cleanStaff(b) {
  const name = String(b.name || '').trim().slice(0, 160);
  if (!name || !Object.hasOwn(SURVEYS, b.template)) return null;
  return { name, position: String(b.position || '').trim().slice(0, 500), dept: String(b.dept || '').trim().slice(0, 160), template: b.template, kind: b.kind === 'point' ? 'point' : 'person', active: b.active ? 1 : 0 };
}
async function readJson(req) {
  if (!String(req.headers.get('content-type')).startsWith('application/json')) throw fail(415, 'type');
  const text = await req.text();
  if (text.length > 32 * 1024) throw fail(413, 'big');
  try { return JSON.parse(text) || {}; } catch { throw fail(400, 'json'); }
}
async function page(env, name, req) {
  const r = await env.ASSETS.fetch(new URL('/' + name, req.url));
  return (await r.text()).replaceAll('{{V}}', env.ASSET_VERSION || '1');
}
const csv = (rows) => '﻿' + rows.map((r) => r.map((v) => { let s = v === null || v === undefined ? '' : String(v); if (/^-?\d+(\.\d+)?$/.test(s)) return s.replace('.', ','); if (/^[=+\-@]/.test(s)) s = "'" + s; return /[";\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; }).join(';')).join('\r\n');
const phoneCell = (p) => p && p.slice(4).replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
const csvHeaders = (name) => ({ 'Content-Type': 'text/csv; charset=utf-8', 'Content-Disposition': `attachment; filename="${name}"` });

async function adminApi(req, env, url, what, id) {
  const db = env.DB, mq = url.searchParams.get('month') || '', m = /^\d{4}-\d{2}$/.test(mq) ? mq : curMonth();
  if (what === 'me') return json({ name: env.ADMIN_NAME || 'Bosh shifokor', today: nowLocal().slice(0, 10), base: env.PUBLIC_URL || url.origin, month: curMonth(), templates: Object.fromEntries(Object.entries(SURVEYS).map(([k, v]) => [k, v.label])) });
  if (what === 'stats' && req.method === 'GET') {
    const [a, b] = range(m);
    const rows = await all(db, `SELECT s.id,s.code,s.name,s.position,s.dept,s.template,s.kind,s.active, ${AGG} FROM staff s LEFT JOIN response r ON r.staff_id = s.id AND r.created_at >= ? AND r.created_at < ? GROUP BY s.id ORDER BY s.name`, a, b);
    const daily = await all(db, `SELECT substr(created_at,1,10) d, COUNT(*) n, AVG(score) avg FROM response WHERE created_at >= ? AND created_at < ? GROUP BY d ORDER BY d`, a, b);
    const byTpl = await all(db, `SELECT r.template k, ${AGG} FROM response r WHERE r.created_at >= ? AND r.created_at < ? GROUP BY r.template`, a, b);
    const byDept = await all(db, `SELECT s.dept k, ${AGG} FROM response r JOIN staff s ON s.id = r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND s.dept <> '' GROUP BY s.dept`, a, b);
    const pr = prevRange(m);
    return json({
      month: m, totals: await totals(db, [a, b]), prev: { ...(await totals(db, pr.range)), partial: pr.partial, until: pr.until || null },
      byTemplate: byTpl.map((x) => ({ k: x.k, label: SURVEYS[x.k]?.label.uz || x.k, ...finish(x) })),
      byDept: byDept.map((x) => ({ k: x.k, label: x.k, ...finish(x) })),
      daily: daily.map((x) => ({ d: x.d, n: +x.n, avg: x.avg === null ? null : r2(x.avg) })),
      staff: rows.map((r) => ({ id: r.id, code: r.code, name: r.name, position: r.position, dept: r.dept, template: r.template, kind: r.kind, active: !!r.active, ...finish(r) })),
    });
  }
  if (what === 'staff' && id && req.method === 'GET') {
    const s = await one(db, 'SELECT id,code,name,position,dept,template,kind,active FROM staff WHERE id = ?', +id);
    if (!s) return json({ error: 'staff' }, 404);
    const [a, b] = range(m);
    const resp = await all(db, `SELECT ${RESP_COLS} FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? ORDER BY r.created_at DESC`, s.id, a, b);
    const trend = await all(db, `SELECT substr(r.created_at,1,7) m, ${AGG} FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? GROUP BY m ORDER BY m`, s.id, `${shiftMonth(m, -5)}-01 00:00:00`, b);
    return json({ staff: { ...s, active: !!s.active }, month: m, surveys: SURVEYS, opt: OPT, responses: resp.map(parseResp), trend: trend.map((x) => ({ m: x.m, ...finish(x) })) });
  }
  if (what === 'staff' && !id && req.method === 'POST') {
    const s = cleanStaff(await readJson(req));
    if (!s) return json({ error: 'invalid' }, 400);
    for (;;) {
      try {
        const r = await db.prepare('INSERT INTO staff (code,name,position,dept,template,kind,active,created_at) VALUES (?,?,?,?,?,?,?,?)').bind(newCode(), s.name, s.position, s.dept, s.template, s.kind, s.active, nowLocal()).run();
        return json({ id: r.meta.last_row_id }, 201);
      } catch (e) { if (!/UNIQUE/.test(e.message)) throw e; }
    }
  }
  if (what === 'staff' && id && req.method === 'PUT') {
    const s = cleanStaff(await readJson(req));
    if (!s) return json({ error: 'invalid' }, 400);
    await db.prepare('UPDATE staff SET name=?,position=?,dept=?,template=?,kind=?,active=? WHERE id=?').bind(s.name, s.position, s.dept, s.template, s.kind, s.active, +id).run();
    return json({ ok: true });
  }
  if (what === 'comments' && req.method === 'GET') {
    const [a, b] = range(m);
    const rows = await all(db, `SELECT ${RESP_COLS},s.id staff_id,s.name,s.dept FROM response r JOIN staff s ON s.id = r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL) ORDER BY r.created_at DESC LIMIT 1000`, a, b);
    return json({ month: m, surveys: SURVEYS, opt: OPT, items: rows.map(parseResp) });
  }
  if (what === 'export' && req.method === 'GET') {
    const [a, b] = range(m);
    if (url.searchParams.get('type') === 'comments') {
      const rows = await all(db, `SELECT r.created_at,s.name,s.dept,r.score,r.nps,r.flags,r.comment,r.phone,r.dev_model,r.dev_os,r.dev_screen,r.dev_id FROM response r JOIN staff s ON s.id=r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL) ORDER BY r.created_at`, a, b);
      return new Response(csv([['Sana', 'Xodim', 'Boʻlim', 'Baho', 'NPS', 'Ogohlantirish', 'Izoh', 'Telefon (+998)', 'Qurilma', 'OS', 'Ekran', 'Qurilma ID'], ...rows.map((r) => [r.created_at, r.name, r.dept, r.score, r.nps, r.flags, r.comment, phoneCell(r.phone), r.dev_model, r.dev_os, r.dev_screen, r.dev_id])]), { headers: { ...SEC, ...csvHeaders(`izohlar-${m}.csv`) } });
    }
    const rows = await all(db, `SELECT s.name,s.position,s.dept,s.template,s.active, ${AGG} FROM staff s LEFT JOIN response r ON r.staff_id=s.id AND r.created_at >= ? AND r.created_at < ? GROUP BY s.id ORDER BY s.name`, a, b);
    return new Response(csv([['Xodim', 'Lavozim', 'Boʻlim', 'Soʻrovnoma', 'Javoblar', 'Oʻrtacha baho (1–5)', 'NPS', 'Ogohlantirishlar', 'Izohlar'],
      ...rows.filter((r) => r.active || +r.n).map((r) => { const f = finish(r); return [r.name, r.position, r.dept, SURVEYS[r.template]?.label.uz, f.n, f.avg, f.nps, f.flags, f.comments]; })]), { headers: { ...SEC, ...csvHeaders(`hisobot-${m}.csv`) } });
  }
  return json({ error: 'not found' }, 404);
}

const NOT_FOUND = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Topilmadi</title><body style="font:16px system-ui;padding:40px;text-align:center"><h2>Bu QR kod hozir ishlamaydi</h2><p>Iltimos, registraturaga murojaat qiling.</p><p>Бу QR код ҳозир ишламайди. Илтимос, регистратурага мурожаат қилинг.</p><p>Bul QR kod házir islemeydi.</p><p>Этот QR-код сейчас не работает. Обратитесь в регистратуру.</p>';

async function handle(req, env) {
  const url = new URL(req.url), parts = url.pathname.split('/'), db = env.DB;
  const ip = req.headers.get('cf-connecting-ip') || '';
  const secure = url.protocol === 'https:' ? '; Secure' : '';

  if (url.pathname.startsWith('/a/') && req.method === 'GET') {
    const path = url.pathname.slice(2);
    if (path.endsWith('.html') || path.includes('..')) return new Response('not found', { status: 404 });
    const r = await env.ASSETS.fetch(new URL(path, url.origin));
    if (!r.ok) return new Response('not found', { status: 404 });
    const res = new Response(r.body, r);
    res.headers.set('Cache-Control', 'public, max-age=2592000, immutable');
    return res;
  }
  if (parts[1] === 's' && parts[2] && req.method === 'GET') {
    const s = await one(db, 'SELECT name,dept,template,kind FROM staff WHERE code = ? AND active = 1', parts[2]);
    if (!s) return html(NOT_FOUND, 404);
    const data = JSON.stringify({ code: parts[2], name: s.name, dept: s.dept, kind: s.kind, survey: SURVEYS[s.template], opt: OPT }).replace(/</g, '\\u003c');
    const headers = cookies(req).d ? {} : { 'Set-Cookie': `d=${b64url(crypto.getRandomValues(new Uint8Array(12)))}; Path=/; Max-Age=31536000; HttpOnly; SameSite=Lax${secure}` };
    return html((await page(env, 's.html', req)).replace('/*DATA*/null', data), 200, headers);
  }
  if (parts[1] === 'api' && parts[2] === 'r' && parts[3] && req.method === 'POST') {
    if (limited('r:' + ip, 40, 3600e3)) return json({ error: 'rate' }, 429);
    const b = await readJson(req);
    const s = await one(db, 'SELECT id,template FROM staff WHERE code = ? AND active = 1', parts[3]);
    if (!s) return json({ error: 'staff' }, 404);
    const g = grade(s.template, b.a);
    if (!g) return json({ error: 'answers' }, 400);
    const lang = LANGS.includes(b.lang) ? b.lang : 'uz';
    const dev = deviceInfo(req.headers.get('user-agent') || '', b.dev && typeof b.dev === 'object' ? b.dev : {});
    const device = cookies(req).d || 'h' + b64url(await crypto.subtle.digest('SHA-256', enc.encode(ip + req.headers.get('user-agent')))).slice(0, 20);
    const devId = hex(await crypto.subtle.digest('SHA-1', enc.encode(device))).slice(0, 5);
    const at = nowLocal(), comment = String(b.c || '').trim().slice(0, 1000) || null, phone = cleanPhone(b.p);
    if (phone === undefined) return json({ error: 'phone' }, 422);
    try {
      await db.prepare('INSERT INTO response (staff_id,template,lang,answers,score,nps,flags,comment,phone,device,dev_model,dev_os,dev_screen,dev_id,day,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        .bind(s.id, s.template, lang, JSON.stringify(g.answers), g.score, g.nps, g.flags, comment, phone, String(device).slice(0, 32), dev.model, dev.os, dev.screen, devId, at.slice(0, 10), at).run();
    } catch (e) { if (/UNIQUE/.test(e.message)) return json({ error: 'dup' }, 409); throw e; }
    return json({ ok: true }, 201);
  }
  if (url.pathname === '/admin' && req.method === 'GET') return html(await page(env, 'admin.html', req));
  if (url.pathname === '/') return Response.redirect(url.origin + '/admin', 302);
  if (url.pathname === '/api/login' && req.method === 'POST') {
    if (limited('l:' + ip, 8, 900e3)) return json({ error: 'rate' }, 429);
    const b = await readJson(req);
    if (!(await checkPassword(env, b.user, b.pass))) return json({ error: 'login' }, 401);
    buckets.delete('l:' + ip);
    const exp = String(Date.now() + 12 * 3600e3);
    return json({ ok: true }, 200, { 'Set-Cookie': `s=${exp}.${await hmac(env.SESSION_SECRET, exp)}; Path=/; Max-Age=43200; HttpOnly; SameSite=Strict${secure}` });
  }
  if (url.pathname === '/api/logout' && req.method === 'POST') return json({ ok: true }, 200, { 'Set-Cookie': `s=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict${secure}` });
  if (parts[1] === 'api') {
    if (!(await authed(req, env))) return json({ error: 'auth' }, 401);
    return adminApi(req, env, url, parts[2], parts[3]);
  }
  return new Response('not found', { status: 404, headers: SEC });
}

export default {
  async fetch(req, env) {
    try { return await handle(req, env); }
    catch (e) {
      if (e.status >= 400 && e.status < 500) return json({ error: e.message }, e.status);
      console.error(req.method, req.url, e.stack || e);
      return json({ error: 'server' }, 500);
    }
  },
};
