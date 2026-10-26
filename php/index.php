<?php
// Fikr — PHP port of app/app.js for cPanel shared hosting (no Node there). Same URLs, JSON, cookies and pages.
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
// local `php -S … index.php`: let the built-in server serve /a/* files (Apache does this via .htaccess)
if (PHP_SAPI === 'cli-server' && str_starts_with($path, '/a/') && !str_ends_with($path, '.html') && is_file(__DIR__ . urldecode($path))) return false;

date_default_timezone_set('Asia/Tashkent');
header_remove('X-Powered-By');
require __DIR__ . '/surveys.php';

const SEC = ['X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'same-origin', 'X-Frame-Options' => 'DENY'];
const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
const JSON_OUT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
const NOT_FOUND = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Topilmadi</title><body style="font:16px system-ui;padding:40px;text-align:center"><h2>Bu QR kod hozir ishlamaydi</h2><p>Iltimos, registraturaga murojaat qiling.</p><p>Бу QR код ҳозир ишламайди. Илтимос, регистратурага мурожаат қилинг.</p><p>Bul QR kod házir islemeydi.</p><p>Этот QR-код сейчас не работает. Обратитесь в регистратуру.</p>';
const CODE_ABC = 'abcdefghjkmnpqrstuvwxyz23456789';
const MIGRATED = __DIR__ . '/.migrated'; // deleted by every deploy → schema check runs once per deploy
const AGG = 'COUNT(r.id) n, SUM(r.score) ssum, COUNT(r.score) sn, SUM(r.nps >= 9) pro, SUM(r.nps <= 6) det, COUNT(r.nps) npsn,
  COALESCE(SUM(r.flags > 0),0) flags, COUNT(r.comment) comments';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
  config();
  route($path, $method);
} catch (Throwable $e) {
  // message + place only: a trace could carry patient phone numbers into the log
  error_log(date('c') . " $method $path " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) send(500, ['error' => 'server']);
}

function config(): void {
  $c = (require getenv('FIKR_CONFIG') ?: '/home/azama430/fikr-config.php')
    + ['DB_HOST' => 'localhost', 'DB_PORT' => 3306, 'DB_PASS' => '', 'ADMIN_NAME' => '', 'PUBLIC_URL' => '', 'COOKIE_INSECURE' => false];
  foreach (['DB_USER', 'DB_NAME', 'ADMIN_USER', 'ADMIN_HASH', 'SESSION_SECRET'] as $k) if (empty($c[$k])) throw new RuntimeException("Missing config $k");
  foreach ($c as $k => $v) define($k, $v);
  define('SECURE', COOKIE_INSECURE ? '' : '; Secure');
}

/* ---------- db ---------- */
function db(): PDO {
  static $pdo;
  if ($pdo) return $pdo;
  $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  if (!is_file(MIGRATED)) {
    $pdo->query("SELECT GET_LOCK('fikr_migrate', 30)");
    try {
      clearstatcache();
      if (!is_file(MIGRATED)) { migrate($pdo); @touch(MIGRATED); }
    } finally { $pdo->query("SELECT RELEASE_LOCK('fikr_migrate')"); }
  }
  return $pdo;
}
function q(string $sql, array $args = []): array { $st = db()->prepare($sql); $st->execute($args); return $st->fetchAll(); }
function one(string $sql, array $args = []): ?array { return q($sql, $args)[0] ?? null; }
function dup(PDOException $e): bool { return ($e->errorInfo[1] ?? 0) === 1062; }

function migrate(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(12) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, position VARCHAR(500) NOT NULL DEFAULT '',
    dept VARCHAR(160) NOT NULL DEFAULT '', template VARCHAR(32) NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT 'person', active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS response (
    id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT NOT NULL, template VARCHAR(32) NOT NULL, lang CHAR(2) NOT NULL, answers TEXT NOT NULL,
    score DECIMAL(3,2) NULL, nps TINYINT NULL, flags TINYINT NOT NULL DEFAULT 0, comment TEXT NULL, phone VARCHAR(13) NULL, device VARCHAR(32) NOT NULL,
    dev_model VARCHAR(60) NOT NULL DEFAULT '', dev_os VARCHAR(20) NOT NULL DEFAULT '', dev_screen VARCHAR(12) NOT NULL DEFAULT '', day DATE NOT NULL,
    created_at DATETIME NOT NULL, UNIQUE KEY one_per_day (staff_id, device, day), KEY by_staff (staff_id, created_at), KEY by_time (created_at)) DEFAULT CHARSET=utf8mb4");
  try { $db->exec('ALTER TABLE response ADD COLUMN phone VARCHAR(13) NULL AFTER comment'); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1060) throw $e; } // 1060 = ER_DUP_FIELDNAME
  // rate-limit buckets (Node kept these in memory; PHP has no long-lived process)
  $db->exec('CREATE TABLE IF NOT EXISTS hits (k VARCHAR(64) NOT NULL PRIMARY KEY, reset_at DATETIME NOT NULL, n INT NOT NULL) DEFAULT CHARSET=utf8mb4');
  if (!(int) $db->query('SELECT COUNT(*) FROM staff')->fetchColumn()) {
    $seed = json_decode(file_get_contents(__DIR__ . '/seed/staff.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($seed as $s) insert_staff($s);
    error_log('Seeded ' . count($seed) . ' staff');
  }
}

function insert_staff(array $s): int {
  for (;;) {
    $code = '';
    foreach (str_split(random_bytes(7)) as $b) $code .= CODE_ABC[ord($b) % strlen(CODE_ABC)];
    try {
      db()->prepare('INSERT INTO staff (code,name,position,dept,template,kind,active,created_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$code, $s['name'], $s['position'] ?? '', $s['dept'] ?? '', $s['template'], ($s['kind'] ?? '') === 'point' ? 'point' : 'person', truthy($s['active'] ?? null) ? 1 : 0, now_local()]);
      return (int) db()->lastInsertId();
    } catch (PDOException $e) { if (!dup($e)) throw $e; }
  }
}
function clean_staff(array $b): ?array {
  $name = cut(jtrim(str($b['name'] ?? null)), 160);
  $t = $b['template'] ?? null;
  if ($name === '' || !is_string($t) || !isset(SURVEYS[$t])) return null;
  return ['name' => $name, 'position' => cut(jtrim(str($b['position'] ?? null)), 500), 'dept' => cut(jtrim(str($b['dept'] ?? null)), 160),
    'template' => $t, 'kind' => ($b['kind'] ?? null) === 'point' ? 'point' : 'person', 'active' => truthy($b['active'] ?? null) ? 1 : 0];
}

/* ---------- helpers ---------- */
function truthy($v): bool { return !($v === null || $v === false || $v === 0 || $v === 0.0 || $v === ''); } // JS truthiness of a JSON value
function now_local(): string { return date('Y-m-d H:i:s'); }
function now_ms(): int { return (int) floor(microtime(true) * 1000); }
function cur_month(): string { return substr(now_local(), 0, 7); }
function shift_month(string $m, int $d): string {
  $t = (int) substr($m, 0, 4) * 12 + (int) substr($m, 5, 2) - 1 + $d;
  return sprintf('%04d-%02d', intdiv($t, 12), $t % 12 + 1);
}
function month_range(string $m): array { return ["$m-01 00:00:00", shift_month($m, 1) . '-01 00:00:00']; }
function month_of(): string { $m = $_GET['month'] ?? ''; return is_string($m) && preg_match('/^\d{4}-\d{2}\z/', $m) ? $m : cur_month(); }
function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function cookie(string $k): string { $v = $_COOKIE[$k] ?? ''; return is_string($v) ? $v : ''; }
function ip_of(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; } // Apache serves directly: X-Forwarded-For is client-forgeable

function send(int $code, $body, array $headers = []): void {
  $json = !is_string($body);
  http_response_code($code);
  foreach (array_merge(SEC, ['Content-Type' => $json ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'], $headers) as $k => $v) header("$k: $v");
  echo $json ? json_encode($body, JSON_OUT) : $body;
}
function page(string $html, int $code = 200, array $headers = []): void {
  send($code, $html, array_merge(['Content-Type' => 'text/html; charset=utf-8', 'Content-Security-Policy' => CSP], $headers));
}
function halt(int $code, string $error): never { send($code, ['error' => $error]); exit; }

function read_json(): array {
  if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) halt(415, 'type');
  $raw = stream_get_contents(fopen('php://input', 'rb'), 32 * 1024 + 1);
  if (strlen($raw) > 32 * 1024) halt(413, 'big');
  try { $b = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (JsonException) { halt(400, 'json'); }
  return is_array($b) ? $b : [];
}

/* ---------- rate limits (MySQL table; same windows as Node) ---------- */
function limited(string $key, int $max, int $sec): bool {
  $key = substr($key, 0, 64);
  $now = now_local();
  if (random_int(1, 50) === 1) q('DELETE FROM hits WHERE reset_at < ?', [$now]);
  // n is assigned first, so both IFs see the old reset_at
  q('INSERT INTO hits (k,reset_at,n) VALUES (?,?,1) ON DUPLICATE KEY UPDATE n = IF(reset_at < ?, 1, n + 1), reset_at = IF(reset_at < ?, ?, reset_at)',
    [$key, $reset = date('Y-m-d H:i:s', time() + $sec), $now, $now, $reset]);
  return (int) one('SELECT n FROM hits WHERE k = ?', [$key])['n'] > $max;
}

/* ---------- admin session: stateless HMAC cookie (interchangeable with the Node one) ---------- */
function sign(string $v): string { return b64url(hash_hmac('sha256', $v, SESSION_SECRET, true)); }
function authed(): bool {
  [$exp, $sig] = explode('.', cookie('s')) + ['', ''];
  return $sig !== '' && hash_equals(sign($exp), $sig) && (float) $exp > now_ms();
}
function check_password($user, $pass): bool {
  $ok = password_verify(str($pass), ADMIN_HASH);
  return hash_equals(strtolower(ADMIN_USER), strtolower(jtrim(str($user)))) && $ok;
}

/* ---------- aggregates ---------- */
// half-up to 2 decimals; the epsilon stops 3.165 (stored as 3.16499…) from rounding down
function r2($x): float { return js_round((float) $x * 100 + 1e-6) / 100; }
function finish(array $r): array {
  return [
    'n' => (int) $r['n'], 'avg' => (int) $r['sn'] ? r2((float) $r['ssum'] / (int) $r['sn']) : null,
    'nps' => (int) $r['npsn'] ? (int) js_round(((float) $r['pro'] - (float) $r['det']) / (int) $r['npsn'] * 100) : null,
    'flags' => (int) $r['flags'], 'comments' => (int) $r['comments'],
  ];
}
function totals(array $range): array { return finish(one('SELECT ' . AGG . ' FROM response r WHERE r.created_at >= ? AND r.created_at < ?', $range)); }
// For the running month compare like with like: 1–20 Oct against 1–20 Sep, not against all of September.
function prev_range(string $m): array {
  $pm = shift_month($m, -1);
  [$from, $fullEnd] = month_range($pm);
  if ($m !== cur_month()) return ['range' => [$from, $fullEnd], 'partial' => false];
  $now = now_local();
  if ((int) substr($now, 8, 2) > (int) date('t', strtotime("$pm-01"))) return ['range' => [$from, $fullEnd], 'partial' => false];
  return ['range' => [$from, "$pm-" . substr($now, 8)], 'partial' => true, 'until' => "$pm-" . substr($now, 8, 2)];
}
function parse_resp(array $r): array {
  $r['answers'] = json_decode($r['answers']); // object, so an empty one stays {}
  $r['score'] = $r['score'] === null ? null : (float) $r['score'];
  return $r;
}

/* ---------- pages ---------- */
function tpl(string $f): string {
  $v = max(array_map('filemtime', glob(__DIR__ . '/a/*'))); // cache-buster: deploy rewrites every file in a/
  return str_replace('{{V}}', (string) $v, file_get_contents(__DIR__ . "/a/$f"));
}

function patient_page(string $code): void {
  $s = one('SELECT name,dept,template,kind FROM staff WHERE code = ? AND active = 1', [$code]);
  if (!$s) { page(NOT_FOUND, 404); return; }
  $data = ['code' => $code, 'name' => $s['name'], 'dept' => $s['dept'], 'kind' => $s['kind'], 'survey' => SURVEYS[$s['template']] ?? null, 'opt' => OPT];
  $headers = [];
  if (cookie('d') === '') $headers['Set-Cookie'] = 'd=' . b64url(random_bytes(12)) . '; Path=/; Max-Age=31536000; HttpOnly; SameSite=Lax' . SECURE;
  $json = json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
  page(str_replace('/*DATA*/null', $json, tpl('s.html')), 200, $headers);
}

function submit(string $code): void {
  $ip = ip_of();
  if (limited('r:' . $ip, 40, 3600)) halt(429, 'rate');
  $b = read_json();
  $s = one('SELECT id,template FROM staff WHERE code = ? AND active = 1', [$code]);
  if (!$s) halt(404, 'staff');
  $g = grade($s['template'], $b['a'] ?? null);
  if (!$g) halt(400, 'answers');
  $lang = in_array($b['lang'] ?? null, LANGS, true) ? $b['lang'] : 'uz';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $dev = device_info($ua ?? '', is_array($b['dev'] ?? null) ? $b['dev'] : []);
  $comment = cut(jtrim(str($b['c'] ?? null)), 1000);
  $phone = clean_phone($b['p'] ?? null);
  if ($phone === false) halt(422, 'phone');
  // cookie blocked or cleared: fall back to ip+UA so one phone still can't flood one person in a day
  // ('undefined' mirrors Node's string concat of a missing header, so both servers derive the same id)
  $device = cookie('d') !== '' ? cookie('d') : 'h' . substr(b64url(hash('sha256', $ip . ($ua ?? 'undefined'), true)), 0, 20);
  $at = now_local();
  try {
    q('INSERT INTO response (staff_id,template,lang,answers,score,nps,flags,comment,phone,device,dev_model,dev_os,dev_screen,day,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$s['id'], $s['template'], $lang, json_encode($g['answers'], JSON_OUT), $g['score'], $g['nps'], $g['flags'], $comment === '' ? null : $comment, $phone,
        cut($device, 32), $dev['model'], $dev['os'], $dev['screen'], substr($at, 0, 10), $at]);
  } catch (PDOException $e) {
    if (dup($e)) halt(409, 'dup');
    throw $e;
  }
  send(201, ['ok' => true]);
}

/* ---------- admin api ---------- */
function cell($v): string {
  $s = $v === null ? '' : (string) $v;
  // Excel in uz/ru reads "4.5" as a date (4 May); a decimal comma keeps it a number, and "-12" stays a number, not a formula
  if (preg_match('/^-?\d+(\.\d+)?\z/', $s)) return str_replace('.', ',', $s);
  if (preg_match('/^[=+\-@]/', $s)) $s = "'" . $s; // block formula injection from patient comments
  return preg_match('/[";\n\r]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
}
// '+998…' would read as a formula in Excel; the column header carries the +998
function phone_cell(?string $p): ?string { return $p ? preg_replace('/(\d{2})(\d{3})(\d{2})(\d{2})/', '$1 $2 $3 $4', substr($p, 4), 1) : $p; }
// Excel in ru/uz locales splits on ';' and needs the BOM to read UTF-8
function csv(array $rows): string { return "\u{FEFF}" . implode("\r\n", array_map(fn($r) => implode(';', array_map('cell', $r)), $rows)); }
function csv_headers(string $name): array { return ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"$name\""]; }

function admin_api(string $what, string $id, string $method): void {
  $m = month_of();
  $range = month_range($m);

  if ($what === 'me' && $method === 'GET') {
    send(200, ['name' => ADMIN_NAME ?: 'Bosh shifokor', 'today' => substr(now_local(), 0, 10), 'base' => PUBLIC_URL ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? ''),
      'month' => cur_month(), 'templates' => array_map(fn($s) => $s['label'], SURVEYS)]);
    return;
  }

  if ($what === 'stats' && $method === 'GET') {
    $rows = q('SELECT s.id,s.code,s.name,s.position,s.dept,s.template,s.kind,s.active, ' . AGG . '
      FROM staff s LEFT JOIN response r ON r.staff_id = s.id AND r.created_at >= ? AND r.created_at < ?
      GROUP BY s.id ORDER BY s.name', $range);
    $daily = q("SELECT DATE_FORMAT(created_at,'%Y-%m-%d') d, COUNT(*) n, AVG(score) avg FROM response
      WHERE created_at >= ? AND created_at < ? GROUP BY d ORDER BY d", $range);
    $byTpl = q('SELECT r.template k, ' . AGG . ' FROM response r WHERE r.created_at >= ? AND r.created_at < ? GROUP BY r.template', $range);
    $byDept = q('SELECT s.dept k, ' . AGG . " FROM response r JOIN staff s ON s.id = r.staff_id
      WHERE r.created_at >= ? AND r.created_at < ? AND s.dept <> '' GROUP BY s.dept", $range);
    $pr = prev_range($m);
    send(200, [
      'month' => $m, 'totals' => totals($range), 'prev' => totals($pr['range']) + ['partial' => $pr['partial'], 'until' => $pr['until'] ?? null],
      'byTemplate' => array_map(fn($x) => ['k' => $x['k'], 'label' => SURVEYS[$x['k']]['label']['uz'] ?? $x['k']] + finish($x), $byTpl),
      'byDept' => array_map(fn($x) => ['k' => $x['k'], 'label' => $x['k']] + finish($x), $byDept),
      'daily' => array_map(fn($x) => ['d' => $x['d'], 'n' => (int) $x['n'], 'avg' => $x['avg'] === null ? null : r2($x['avg'])], $daily),
      'staff' => array_map(fn($r) => ['id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'position' => $r['position'], 'dept' => $r['dept'],
        'template' => $r['template'], 'kind' => $r['kind'], 'active' => (bool) $r['active']] + finish($r), $rows),
    ]);
    return;
  }

  if ($what === 'staff' && $id !== '' && $method === 'GET') {
    $s = one('SELECT id,code,name,position,dept,template,kind,active FROM staff WHERE id = ?', [(int) $id]);
    if (!$s) halt(404, 'staff');
    $resp = q('SELECT r.id,r.template,r.lang,r.answers,r.score,r.nps,r.flags,r.comment,r.phone,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? ORDER BY r.created_at DESC', [$s['id'], ...$range]);
    $trend = q("SELECT DATE_FORMAT(r.created_at,'%Y-%m') m, " . AGG . ' FROM response r WHERE r.staff_id = ? AND r.created_at >= ? AND r.created_at < ? GROUP BY m ORDER BY m',
      [$s['id'], shift_month($m, -5) . '-01 00:00:00', $range[1]]);
    send(200, ['staff' => $s, 'month' => $m, 'surveys' => SURVEYS, 'opt' => OPT, 'responses' => array_map('parse_resp', $resp), 'trend' => array_map(fn($t) => ['m' => $t['m']] + finish($t), $trend)]);
    return;
  }

  if ($what === 'staff' && $method === 'POST' && $id === '') {
    $s = clean_staff(read_json());
    if (!$s) halt(400, 'invalid');
    send(201, ['id' => insert_staff($s)]);
    return;
  }
  if ($what === 'staff' && $id !== '' && $method === 'PUT') {
    $s = clean_staff(read_json());
    if (!$s) halt(400, 'invalid');
    q('UPDATE staff SET name=?,position=?,dept=?,template=?,kind=?,active=? WHERE id=?', [$s['name'], $s['position'], $s['dept'], $s['template'], $s['kind'], $s['active'], (int) $id]);
    send(200, ['ok' => true]);
    return;
  }

  if ($what === 'comments' && $method === 'GET') {
    $rows = q('SELECT r.id,r.score,r.nps,r.flags,r.comment,r.phone,r.answers,r.template,r.lang,r.created_at,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id,s.id staff_id,s.name,s.dept
      FROM response r JOIN staff s ON s.id = r.staff_id WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL)
      ORDER BY r.created_at DESC LIMIT 1000', $range);
    send(200, ['month' => $m, 'surveys' => SURVEYS, 'opt' => OPT, 'items' => array_map('parse_resp', $rows)]);
    return;
  }

  if ($what === 'export' && $method === 'GET') {
    if (($_GET['type'] ?? null) === 'comments') {
      $rows = q('SELECT r.created_at,s.name,s.dept,r.score,r.nps,r.flags,r.comment,r.phone,r.dev_model,r.dev_os,r.dev_screen,LEFT(SHA1(r.device),5) dev_id FROM response r JOIN staff s ON s.id=r.staff_id
        WHERE r.created_at >= ? AND r.created_at < ? AND (r.comment IS NOT NULL OR r.flags > 0 OR r.phone IS NOT NULL) ORDER BY r.created_at', $range);
      send(200, csv([['Sana', 'Xodim', 'Boʻlim', 'Baho', 'NPS', 'Ogohlantirish', 'Izoh', 'Telefon (+998)', 'Qurilma', 'OS', 'Ekran', 'Qurilma ID'],
        ...array_map(fn($r) => [$r['created_at'], $r['name'], $r['dept'], $r['score'], $r['nps'], $r['flags'], $r['comment'], phone_cell($r['phone']), $r['dev_model'], $r['dev_os'], $r['dev_screen'], $r['dev_id']], $rows)]),
        csv_headers("izohlar-$m.csv"));
      return;
    }
    $rows = q('SELECT s.name,s.position,s.dept,s.template,s.active, ' . AGG . ' FROM staff s
      LEFT JOIN response r ON r.staff_id=s.id AND r.created_at >= ? AND r.created_at < ? GROUP BY s.id ORDER BY s.name', $range);
    $out = [['Xodim', 'Lavozim', 'Boʻlim', 'Soʻrovnoma', 'Javoblar', 'Oʻrtacha baho (1–5)', 'NPS', 'Ogohlantirishlar', 'Izohlar']];
    foreach ($rows as $r) {
      if (!$r['active'] && !(int) $r['n']) continue;
      $f = finish($r);
      $out[] = [$r['name'], $r['position'], $r['dept'], SURVEYS[$r['template']]['label']['uz'] ?? null, $f['n'], $f['avg'], $f['nps'], $f['flags'], $f['comments']];
    }
    send(200, csv($out), csv_headers("hisobot-$m.csv"));
    return;
  }

  send(404, ['error' => 'not found']);
}

/* ---------- router ---------- */
function route(string $path, string $method): void {
  $p = explode('/', $path) + ['', '', '', ''];

  // reached only when Apache found no file (or an .html): real /a/* files never get here
  if (str_starts_with($path, '/a/') && $method === 'GET') { send(404, 'not found'); return; }
  if ($p[1] === 's' && $p[2] !== '' && $method === 'GET') { patient_page($p[2]); return; }
  if ($p[1] === 'api' && $p[2] === 'r' && $p[3] !== '' && $method === 'POST') { submit($p[3]); return; }
  if ($path === '/admin' && $method === 'GET') { page(tpl('admin.html')); return; }
  if ($path === '/') { http_response_code(302); header('Location: /admin'); return; }

  if ($path === '/api/login' && $method === 'POST') {
    $ip = ip_of();
    if (limited('l:' . $ip, 8, 900)) halt(429, 'rate');
    $b = read_json();
    if (!check_password($b['user'] ?? null, $b['pass'] ?? null)) halt(401, 'login');
    q('DELETE FROM hits WHERE k = ?', [substr('l:' . $ip, 0, 64)]);
    $exp = (string) (now_ms() + 12 * 3600 * 1000);
    send(200, ['ok' => true], ['Set-Cookie' => "s=$exp." . sign($exp) . '; Path=/; Max-Age=43200; HttpOnly; SameSite=Strict' . SECURE]);
    return;
  }
  if ($path === '/api/logout' && $method === 'POST') { send(200, ['ok' => true], ['Set-Cookie' => 's=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict' . SECURE]); return; }

  if ($p[1] === 'api') {
    if (!authed()) halt(401, 'auth');
    // admin writes need a JSON body (read_json enforces it) and SameSite=Strict blocks cross-site cookies → no CSRF token needed
    admin_api($p[2], $p[3], $method);
    return;
  }
  send(404, 'not found');
}
