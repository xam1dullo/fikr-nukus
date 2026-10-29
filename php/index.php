<?php
// Fikr — patient feedback server, PHP port of app/app.js. Apache serves real /a/* files; everything else lands here.
require __DIR__ . '/surveys.php';

date_default_timezone_set('Asia/Tashkent');
header_remove('X-Powered-By');
$C = [];

const SEC = ['X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'same-origin', 'X-Frame-Options' => 'DENY'];
const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
const MIME = ['.js' => 'text/javascript', '.css' => 'text/css', '.woff2' => 'font/woff2', '.svg' => 'image/svg+xml', '.png' => 'image/png', '.html' => 'text/html; charset=utf-8'];
const NOT_FOUND = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Topilmadi</title><body style="font:16px system-ui;padding:40px;text-align:center"><h2>Bu QR kod hozir ishlamaydi</h2><p>Iltimos, registraturaga murojaat qiling.</p><p>Бу QR код ҳозир ишламайди. Илтимос, регистратурага мурожаат қилинг.</p><p>Bul QR kod házir islemeydi.</p><p>Этот QR-код сейчас не работает. Обратитесь в регистратуру.</p>';
const CODE_ABC = 'abcdefghjkmnpqrstuvwxyz23456789';
const AGG = "COUNT(r.id) n, SUM(r.score) ssum, COUNT(r.score) sn, SUM(r.nps >= 9) pro, SUM(r.nps <= 6) det, COUNT(r.nps) npsn,
  COALESCE(SUM(r.flags > 0),0) flags, COUNT(r.comment) comments";

final class HttpError extends Exception {}

function cfg(string $k) { return $GLOBALS['C'][$k] ?? null; }
function nowLocal(): string { return date('Y-m-d H:i:s'); }
function curMonth(): string { return substr(nowLocal(), 0, 7); }
function shiftMonth(string $m, int $d): string { [$y, $mo] = array_map('intval', explode('-', $m)); return gmdate('Y-m', gmmktime(0, 0, 0, $mo + $d, 1, $y)); }
function mrange(string $m): array { return ["$m-01 00:00:00", shiftMonth($m, 1) . '-01 00:00:00']; }
function monthOf(): string { $q = $_GET['month'] ?? ''; return is_string($q) && preg_match('/^\d{4}-\d{2}$/D', $q) ? $q : curMonth(); }

/* ---------- JS value semantics ---------- */
function truthy($v): bool { return !in_array($v, [null, false, 0, 0.0, ''], true); }
function orStr($v): string { return truthy($v) ? jsString($v) : ''; } // String(v || '')
function jsTrim(string $s): string {
  $ws = '[\s\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';
  return (string) preg_replace("/^$ws+|$ws+\$/uD", '', $s);
}
function cut(string $s, int $n): string { return mb_substr(jsTrim($s), 0, $n, 'UTF-8'); }
function jsNum(string $s) { $t = jsTrim($s); return $t === '' ? 0 : (is_numeric($t) ? $t + 0 : null); } // null = NaN
function idNum(string $s) { $n = jsNum($s); if ($n === null) throw new RuntimeException("NaN id $s"); return $n; } // mysql2 sends NaN → SQL error → 500

/* ---------- http ---------- */
function jenc($x): string { return json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR); }
function send(int $code, $body, array $headers = []) {
  $json = !is_string($body);
  $out = $json ? jenc($body) : $body;
  http_response_code($code);
  foreach (SEC + ['Content-Type' => $json ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'] as $k => $v) header("$k: " . ($headers[$k] ?? $v));
  foreach ($headers as $k => $v) if (!isset(SEC[$k]) && $k !== 'Content-Type' && $k !== 'Cache-Control') header("$k: $v", $k !== 'Set-Cookie');
  echo $out;
}
function page(string $html, int $code = 200, array $headers = []) { return send($code, $html, ['Content-Type' => MIME['.html'], 'Content-Security-Policy' => CSP] + $headers); }

function readJson(): array {
  if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'undefined', 'application/json')) throw new HttpError('type', 415);
  $raw = (string) file_get_contents('php://input');
  if (strlen($raw) > 32 * 1024) throw new HttpError('big', 413);
  try { $b = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (JsonException) { throw new HttpError('json', 400); }
  return is_array($b) ? $b : [];
}
function cookies(): array {
  $o = [];
  foreach (explode(';', $_SERVER['HTTP_COOKIE'] ?? '') as $c) { $p = explode('=', jsTrim($c)); if ($p[0] !== '') $o[$p[0]] = implode('=', array_slice($p, 1)); }
  return $o;
}
function ipOf(): string { $f = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]); return $f !== '' ? $f : ($_SERVER['REMOTE_ADDR'] ?? ''); }
function secure(): string { return cfg('COOKIE_INSECURE') ? '' : '; Secure'; }
function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

/* ---------- db ---------- */
function db(): PDO {
  static $pdo;
  return $pdo ??= new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('DB_HOST') ?: 'localhost', (int) (cfg('DB_PORT') ?: 3306), cfg('DB_NAME')), cfg('DB_USER'), (string) cfg('DB_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}
function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute(array_values($p)); return $st; }
function one(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function errno(PDOException $e): int { return (int) ($e->errorInfo[1] ?? 0); }

/* ---------- rate limits (hits table; PHP has no long-lived process) ---------- */
function hitKey(string $k): string { return strlen($k) <= 64 && mb_check_encoding($k, 'UTF-8') ? $k : substr($k, 0, 2) . sha1($k); }
function limited(string $key, int $max, int $sec): bool {
  $k = hitKey($key); $now = nowLocal();
  if (random_int(1, 100) === 1) q('DELETE FROM hits WHERE reset_at < ?', [$now]);
  q('INSERT INTO hits (k,reset_at,n) VALUES (?,?,1) ON DUPLICATE KEY UPDATE n = IF(reset_at < ?, 1, n + 1), reset_at = IF(reset_at < ?, VALUES(reset_at), reset_at)',
    [$k, date('Y-m-d H:i:s', time() + $sec), $now, $now]);
  return (int) q('SELECT n FROM hits WHERE k = ?', [$k])->fetchColumn() > $max;
}

/* ---------- admin session: stateless HMAC cookie ---------- */
function sign(string $v): string { return b64url(hash_hmac('sha256', $v, (string) cfg('SESSION_SECRET'), true)); }
function authed(): bool {
  $p = explode('.', cookies()['s'] ?? '');
  $sig = $p[1] ?? '';
  return $sig !== '' && hash_equals(sign($p[0]), $sig) && ($e = jsNum($p[0])) !== null && $e > microtime(true) * 1000;
}
function checkPassword($user, $pass): bool {
  $okPass = password_verify(orStr($pass), (string) cfg('ADMIN_HASH'));
  $okUser = hash_equals(mb_strtolower((string) cfg('ADMIN_USER'), 'UTF-8'), mb_strtolower(jsTrim(orStr($user)), 'UTF-8'));
  return $okPass && $okUser;
}

/* ---------- schema ---------- */
function migrate(): void {
  db()->exec("CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(12) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, position VARCHAR(500) NOT NULL DEFAULT '',
    dept VARCHAR(160) NOT NULL DEFAULT '', template VARCHAR(32) NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT 'person', active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS response (
    id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT NOT NULL, template VARCHAR(32) NOT NULL, lang CHAR(2) NOT NULL, answers TEXT NOT NULL,
    score DECIMAL(3,2) NULL, nps TINYINT NULL, flags TINYINT NOT NULL DEFAULT 0, comment TEXT NULL, phone VARCHAR(13) NULL, device VARCHAR(32) NOT NULL,
    dev_model VARCHAR(60) NOT NULL DEFAULT '', dev_os VARCHAR(20) NOT NULL DEFAULT '', dev_screen VARCHAR(12) NOT NULL DEFAULT '', day DATE NOT NULL,
    created_at DATETIME NOT NULL, UNIQUE KEY one_per_day (staff_id, device, day), KEY by_staff (staff_id, created_at), KEY by_time (created_at)) DEFAULT CHARSET=utf8mb4");
  db()->exec('CREATE TABLE IF NOT EXISTS hits (k VARCHAR(64) PRIMARY KEY, reset_at DATETIME NOT NULL, n INT NOT NULL) DEFAULT CHARSET=utf8mb4');
  try { db()->exec('ALTER TABLE response ADD COLUMN phone VARCHAR(13) NULL AFTER comment'); } catch (PDOException $e) { if (errno($e) !== 1060) throw $e; }
  if (!(int) q('SELECT COUNT(*) n FROM staff')->fetchColumn()) {
    $seed = json_decode(file_get_contents(__DIR__ . '/seed/staff.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($seed as $s) insertStaff($s);
    error_log('Seeded ' . count($seed) . ' staff');
  }
}
function newCode(): string { return implode('', array_map(fn($b) => CODE_ABC[ord($b) % strlen(CODE_ABC)], str_split(random_bytes(7)))); }
function insertStaff(array $s): int {
  for (;;) {
    try {
      q('INSERT INTO staff (code,name,position,dept,template,kind,active,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [newCode(), $s['name'] ?? null, truthy($s['position'] ?? null) ? $s['position'] : '', truthy($s['dept'] ?? null) ? $s['dept'] : '', $s['template'] ?? null,
          ($s['kind'] ?? null) === 'point' ? 'point' : 'person', truthy($s['active'] ?? null) ? 1 : 0, nowLocal()]);
      return (int) db()->lastInsertId();
    } catch (PDOException $e) { if (errno($e) !== 1062) throw $e; }
  }
}
function cleanStaff(array $b): ?array {
  $name = cut(orStr($b['name'] ?? null), 160);
  $t = $b['template'] ?? null;
  if ($name === '' || !is_string($t) || !array_key_exists($t, surveys()['SURVEYS'])) return null;
  return ['name' => $name, 'position' => cut(orStr($b['position'] ?? null), 500), 'dept' => cut(orStr($b['dept'] ?? null), 160), 'template' => $t,
    'kind' => ($b['kind'] ?? null) === 'point' ? 'point' : 'person', 'active' => truthy($b['active'] ?? null) ? 1 : 0];
}

/* ---------- aggregates ---------- */
// half-up to 2 decimals; the epsilon stops 3.165 (stored as 3.16499…) from rounding down
function r2($x) { return num(jsRound((float) $x * 100 + 1e-6) / 100); }
function finish(array $r): array {
  $sn = (float) $r['sn']; $npsn = (float) $r['npsn'];
  return [
    'n' => (int) $r['n'], 'avg' => $sn ? r2((float) $r['ssum'] / $sn) : null,
    'nps' => $npsn ? jsRound((((float) $r['pro'] - (float) $r['det']) / $npsn) * 100) : null, 'flags' => (int) $r['flags'], 'comments' => (int) $r['comments'],
  ];
}
function totals(array $rg): array { return finish(one('SELECT ' . AGG . ' FROM response r WHERE r.created_at >= ? AND r.created_at < ?', $rg)); }
// For the running month compare like with like: 1–20 Oct against 1–20 Sep, not against all of September.
function prevRange(string $m): array {
  $pm = shiftMonth($m, -1); [$from, $fullEnd] = mrange($pm);
  if ($m !== curMonth()) return ['range' => [$from, $fullEnd], 'partial' => false];
  $now = nowLocal(); $day = (int) substr($now, 8, 2); $last = (int) gmdate('t', gmmktime(0, 0, 0, (int) substr($pm, 5), 1, (int) substr($pm, 0, 4)));
  if ($day > $last) return ['range' => [$from, $fullEnd], 'partial' => false];
  return ['range' => [$from, "$pm-" . substr($now, 8)], 'partial' => true, 'until' => "$pm-" . substr($now, 8, 2)];
}
function parseResp(array $r): array {
  $r['answers'] = json_decode($r['answers'], false, 512, JSON_THROW_ON_ERROR); // objects, so {} and key order survive re-encoding
  $r['score'] = $r['score'] === null ? null : num((float) $r['score']);
  return $r;
}

/* ---------- pages ---------- */
function version(): string { static $v; return $v ??= (string) max(array_map('filemtime', array_filter(glob(__DIR__ . '/a/*') ?: [], 'is_file')) ?: [0]); }
function tpl(string $f): string { return str_replace('{{V}}', version(), file_get_contents(__DIR__ . '/a/' . $f)); }
function jsonForScript($o): string { return json_encode($o, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR); } // </script> can't break out

function patientPage(string $code) {
  $s = one('SELECT name,dept,template,kind FROM staff WHERE code = ? AND active = 1', [$code]);
  if (!$s) return page(NOT_FOUND, 404);
  $S = surveys();
  $data = ['code' => $code, 'name' => $s['name'], 'dept' => $s['dept'], 'kind' => $s['kind']]
    + (array_key_exists($s['template'], $S['SURVEYS']) ? ['survey' => $S['SURVEYS'][$s['template']]] : []) + ['opt' => $S['OPT']];
  $headers = [];
  if ((cookies()['d'] ?? '') === '') $headers['Set-Cookie'] = 'd=' . b64url(random_bytes(12)) . '; Path=/; Max-Age=31536000; HttpOnly; SameSite=Lax' . secure();
  $html = tpl('s.html');
  if (($i = strpos($html, '/*DATA*/null')) !== false) $html = substr_replace($html, jsonForScript($data), $i, strlen('/*DATA*/null'));
  page($html, 200, $headers);
}

function submit(string $code) {
  $ip = ipOf();
  if (limited('r:' . $ip, 40, 3600)) return send(429, ['error' => 'rate']);
  $b = readJson();
  $s = one('SELECT id,template FROM staff WHERE code = ? AND active = 1', [$code]);
  if (!$s) return send(404, ['error' => 'staff']);
  $g = grade($s['template'], $b['a'] ?? null);
  if ($g === null) return send(400, ['error' => 'answers']);
  $lang = in_array($b['lang'] ?? null, surveys()['LANGS'], true) ? $b['lang'] : 'uz';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $dev = deviceInfo($ua ?? '', is_array($b['dev'] ?? null) ? $b['dev'] : []);
  $comment = cut(orStr($b['c'] ?? null), 1000); $comment = $comment === '' ? null : $comment;
  $phone = cleanPhone($b['p'] ?? null);
  if ($phone === false) return send(422, ['error' => 'phone']);
  // cookie blocked or cleared: fall back to ip+UA so one phone still can't flood one person in a day
  $device = cookies()['d'] ?? '';
  if ($device === '') $device = 'h' . substr(b64url(hash('sha256', $ip . ($ua ?? 'undefined'), true)), 0, 20);
  $at = nowLocal();
  try {
    q('INSERT INTO response (staff_id,template,lang,answers,score,nps,flags,comment,phone,device,dev_model,dev_os,dev_screen,day,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$s['id'], $s['template'], $lang, jenc($g['answers']), $g['score'], $g['nps'], $g['flags'], $comment, $phone, substr($device, 0, 32), $dev['model'], $dev['os'], $dev['screen'], substr($at, 0, 10), $at]);
  } catch (PDOException $e) {
    if (errno($e) === 1062) return send(409, ['error' => 'dup']);
    throw $e;
  }
  send(201, ['ok' => true]);
}

/* ---------- admin api ---------- */
function cell($v): string {
  $s = $v === null ? '' : (is_int($v) || is_float($v) ? (string) num($v) : jsString($v));
  // Excel in uz/ru reads "4.5" as a date (4 May); a decimal comma keeps it a number, and "-12" stays a number, not a formula
  if (preg_match('/^-?\d+(\.\d+)?$/D', $s)) return preg_replace('/\./', ',', $s, 1);
  if (preg_match('/^[=+\-@]/', $s)) $s = "'" . $s; // block formula injection from patient comments
  return preg_match('/[";\n\r]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
}
// '+998…' would read as a formula in Excel; the column header carries the +998
function phoneCell(?string $p) { return $p ? preg_replace('/(\d{2})(\d{3})(\d{2})(\d{2})/', '$1 $2 $3 $4', substr($p, 4), 1) : $p; }
// Excel in ru/uz locales splits on ';' and needs the BOM to read UTF-8
function csv(array $rows): string { return "\u{FEFF}" . implode("\r\n", array_map(fn($r) => implode(';', array_map('cell', $r)), $rows)); }
function csvHeaders(string $name): array { return ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"$name\""]; }
function tplLabel(?string $k) { $l = surveys()['SURVEYS'][$k ?? '']['label']['uz'] ?? null; return $l; }

function adminApi(string $method, array $parts) {
  $m = monthOf(); $rg = mrange($m); $S = surveys();
  $what = $parts[2] ?? null; $id = $parts[3] ?? ''; // /api/<what>/<id>

  if ($what === 'me' && $method === 'GET') {
    return send(200, ['name' => cfg('ADMIN_NAME') ?: 'Bosh shifokor', 'today' => substr(nowLocal(), 0, 10), 'base' => cfg('PUBLIC_URL') ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'undefined'),
      'month' => curMonth(), 'templates' => array_map(fn($v) => $v['label'], $S['SURVEYS'])]);
  }

  if ($what === 'stats' && $method === 'GET') {
    $rows = all('SELECT s.id,s.code,s.name,s.position,s.dept,s.template,s.kind,s.active, ' . AGG . '
      FROM staff s LEFT JOIN response r ON r.staff_id = s.id AND r.created_at >= ? AND r.created_at < ?
      GROUP BY s.id ORDER BY s.name', $rg);
    $daily = all("SELECT DATE_FORMAT(created_at,'%Y-%m-%d') d, COUNT(*) n, AVG(score) avg FROM response
      WHERE created_at >= ? AND created_at < ? GROUP BY d ORDER BY d", $rg);
    $byTpl = all('SELECT r.template k, ' . AGG . ' FROM response r WHERE r.created_at >= ? AND r.created_at < ? GROUP BY r.template', $rg);
    $byDept = all('SELECT s.dept k, ' . AGG . " FROM response r JOIN staff s ON s.id = r.staff_id
      WHERE r.created_at >= ? AND r.created_at < ? AND s.dept <> '' GROUP BY s.dept", $rg);
    $pr = prevRange($m);
    return send(200, [
      'month' => $m, 'totals' => totals($rg), 'prev' => totals($pr['range']) + ['partial' => $pr['partial'], 'until' => $pr['until'] ?? null],
      'byTemplate' => array_map(fn($x) => ['k' => $x['k'], 'label' => truthy(tplLabel($x['k'])) ? tplLabel($x['k']) : $x['k']] + finish($x), $byTpl),
      'byDept' => array_map(fn($x) => ['k' => $x['k'], 'label' => $x['k']] + finish($x), $byDept),
      'daily' => array_map(fn($x) => ['d' => $x['d'], 'n' => (int) $x['n'], 'avg' => $x['avg'] === null ? null : r2($x['avg'])], $daily),
      'staff' => array_map(fn($r) => ['id' => $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'position' => $r['position'], 'dept' => $r['dept'], 'template' => $r['template'],
        'kind' => $r['kind'], 'active' => (bool) $r['active']] + finish($r), $rows),
    ]);
  }

  if ($what === 'staff' && $id !== '' && $method === 'GET') {
    $s = one('SELECT id,code,name,position,dept,template,kind,active FROM staff WHERE id = ?', [idNum($id)]);
    if (!$s) return send(404, ['error' => 'staff']);
    $resp = all('SELECT r.id,r.template,r.lang,r.answers,r.score,r.nps,r.flags,r.comment,r.phone,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? ORDER BY r.created_at DESC', [$s['id'], ...$rg]);
    $trend = all("SELECT DATE_FORMAT(r.created_at,'%Y-%m') m, " . AGG . ' FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? GROUP BY m ORDER BY m',
      [$s['id'], shiftMonth($m, -5) . '-01 00:00:00', $rg[1]]);
    return send(200, ['staff' => $s, 'month' => $m, 'surveys' => $S['SURVEYS'], 'opt' => $S['OPT'], 'responses' => array_map('parseResp', $resp), 'trend' => array_map(fn($t) => ['m' => $t['m']] + finish($t), $trend)]);
  }

  if ($what === 'staff' && $method === 'POST' && $id === '') {
    $s = cleanStaff(readJson());
    if (!$s) return send(400, ['error' => 'invalid']);
    return send(201, ['id' => insertStaff($s)]);
  }
  if ($what === 'staff' && $id !== '' && $method === 'PUT') {
    $s = cleanStaff(readJson());
    if (!$s) return send(400, ['error' => 'invalid']);
    q('UPDATE staff SET name=?,position=?,dept=?,template=?,kind=?,active=? WHERE id=?', [$s['name'], $s['position'], $s['dept'], $s['template'], $s['kind'], $s['active'], idNum($id)]);
    return send(200, ['ok' => true]);
  }

  if ($what === 'comments' && $method === 'GET') {
    $rows = all('SELECT r.id,r.score,r.nps,r.flags,r.comment,r.phone,r.answers,r.template,r.lang,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id,s.id staff_id,s.name,s.dept
      FROM response r JOIN staff s ON s.id = r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL)
      ORDER BY r.created_at DESC LIMIT 1000', $rg);
    return send(200, ['month' => $m, 'surveys' => $S['SURVEYS'], 'opt' => $S['OPT'], 'items' => array_map('parseResp', $rows)]);
  }

  if ($what === 'export' && $method === 'GET') {
    if (($_GET['type'] ?? null) === 'comments') {
      $rows = all('SELECT r.created_at,s.name,s.dept,r.score,r.nps,r.flags,r.comment,r.phone,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r JOIN staff s ON s.id=r.staff_id
        WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL) ORDER BY r.created_at', $rg);
      return send(200, csv([['Sana', 'Xodim', 'Boʻlim', 'Baho', 'NPS', 'Ogohlantirish', 'Izoh', 'Telefon (+998)', 'Qurilma', 'OS', 'Ekran', 'Qurilma ID'],
        ...array_map(fn($r) => [$r['created_at'], $r['name'], $r['dept'], $r['score'], $r['nps'], $r['flags'], $r['comment'], phoneCell($r['phone']), $r['dev_model'], $r['dev_os'], $r['dev_screen'], $r['dev_id']], $rows)]),
        csvHeaders("izohlar-$m.csv"));
    }
    $rows = all('SELECT s.name,s.position,s.dept,s.template,s.active, ' . AGG . ' FROM staff s
      LEFT JOIN response r ON r.staff_id=s.id AND r.created_at >= ? AND r.created_at < ? GROUP BY s.id ORDER BY s.name', $rg);
    return send(200, csv([['Xodim', 'Lavozim', 'Boʻlim', 'Soʻrovnoma', 'Javoblar', 'Oʻrtacha baho (1–5)', 'NPS', 'Ogohlantirishlar', 'Izohlar'],
      ...array_map(function ($r) { $f = finish($r); return [$r['name'], $r['position'], $r['dept'], tplLabel($r['template']), $f['n'], $f['avg'], $f['nps'], $f['flags'], $f['comments']]; },
        array_values(array_filter($rows, fn($r) => truthy($r['active']) || (int) $r['n'])))]),
      csvHeaders("hisobot-$m.csv"));
  }

  send(404, ['error' => 'not found']);
}

/* ---------- router ---------- */
function route(string $method, string $path, array $parts) {
  if (($parts[1] ?? '') === 's' && ($parts[2] ?? '') !== '' && $method === 'GET') return patientPage($parts[2]);
  if (($parts[1] ?? '') === 'api' && ($parts[2] ?? '') === 'r' && ($parts[3] ?? '') !== '' && $method === 'POST') return submit($parts[3]);
  if ($path === '/admin' && $method === 'GET') return page(tpl('admin.html'));
  if ($path === '/') { ini_set('default_mimetype', ''); http_response_code(302); header('Location: /admin'); return; }

  if ($path === '/api/login' && $method === 'POST') {
    $ip = ipOf();
    if (limited('l:' . $ip, 8, 900)) return send(429, ['error' => 'rate']);
    $b = readJson();
    if (!checkPassword($b['user'] ?? null, $b['pass'] ?? null)) return send(401, ['error' => 'login']);
    q('DELETE FROM hits WHERE k = ?', [hitKey('l:' . $ip)]);
    $exp = (string) (int) (microtime(true) * 1000 + 12 * 3600 * 1000);
    return send(200, ['ok' => true], ['Set-Cookie' => "s=$exp." . sign($exp) . '; Path=/; Max-Age=43200; HttpOnly; SameSite=Strict' . secure()]);
  }
  if ($path === '/api/logout' && $method === 'POST') return send(200, ['ok' => true], ['Set-Cookie' => 's=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict' . secure()]);

  if (($parts[1] ?? '') === 'api') {
    if (!authed()) return send(401, ['error' => 'auth']);
    // admin writes need a JSON body (readJson enforces it) and SameSite=Strict blocks cross-site cookies → no CSRF token needed
    return adminApi($method, $parts);
  }
  send(404, 'not found');
}

// /a/<file> → absolute path, or null when it would escape a/, is .html, or isn't a file (mirrors path.normalize + startsWith in app.js)
function staticFile(string $path): ?string {
  $rel = rawurldecode(substr($path, 3)); $segs = [];
  if (str_contains($rel, "\0") || str_ends_with($rel, '/')) return null;
  foreach (explode('/', $rel) as $seg) {
    if ($seg === '..') { if (!$segs) return null; array_pop($segs); } elseif ($seg !== '' && $seg !== '.') $segs[] = $seg;
  }
  $file = __DIR__ . '/a/' . implode('/', $segs);
  return $segs && !str_ends_with($file, '.html') && is_file($file) ? $file : null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') $path = '/';
$parts = explode('/', $path);

if (str_starts_with($path, '/a/') && $method === 'GET') {
  $file = staticFile($path);
  if (!$file) return send(404, 'not found');
  if (PHP_SAPI === 'cli-server') return false;
  $b = basename($file); $dot = strrpos($b, '.');
  ini_set('default_charset', ''); // PHP would otherwise append "; charset=UTF-8" to text/* types
  return send(200, file_get_contents($file), ['Content-Type' => MIME[$dot ? substr($b, $dot) : ''] ?? 'application/octet-stream', 'Cache-Control' => 'public, max-age=2592000, immutable']);
}

try {
  $C = require (getenv('FIKR_CONFIG') ?: '/home/azama430/fikr-config.php');
  foreach (['DB_USER', 'DB_NAME', 'ADMIN_USER', 'ADMIN_HASH', 'SESSION_SECRET'] as $k) if (!cfg($k)) throw new RuntimeException("Missing config $k");
  if (!is_file(__DIR__ . '/.migrated')) { migrate(); touch(__DIR__ . '/.migrated'); }
  route($method, $path, $parts);
} catch (HttpError $e) {
  send($e->getCode(), ['error' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log(date('c') . " $method " . ($_SERVER['REQUEST_URI'] ?? '') . " $e");
  if (!headers_sent()) { header_remove('Set-Cookie'); send(500, ['error' => 'server']); }
}
