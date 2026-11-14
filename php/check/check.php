<?php
// Fikr — one-off server diagnostics. Upload the whole check/ folder into the site's docroot, open /check/check.php,
// read the report, then DELETE the folder. Prints no secrets: passwords, DB_PASS, SESSION_SECRET and the hash stay hidden.
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Tashkent');

$rows = [];
$add = function (string $group, string $what, ?bool $ok, string $detail = '') use (&$rows) { $rows[] = [$group, $what, $ok, $detail]; };
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$root = dirname(__DIR__);
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTPS'] ?? '') === '1';

/* ---------- PHP ---------- */
$add('PHP', 'Versiya', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '8.1', '<') ? ' — kamida 8.1 kerak: MultiPHP Manager → 8.3' : ''));
foreach (['pdo_mysql', 'mbstring', 'json'] as $ext) $add('PHP', "Kengaytma $ext", extension_loaded($ext));
$add('PHP', 'HTTPS', $https, $https ? 'ha' : 'yo‘q — saytni https:// bilan oching, aks holda login cookie saqlanmaydi va login sahifasiga qaytaveradi');
$add('PHP', 'display_errors', null, ini_get('display_errors') ?: 'off');

/* ---------- files in the docroot ---------- */
$idx = @file_get_contents("$root/index.php");
$which = $idx === false ? null : (str_contains($idx, 'final class HttpError') ? 'php-port-2' : (str_contains($idx, 'function ip_of') ? 'php-port' : 'nomaʼlum'));
$add('Fayllar', 'Papka', true, $root);
$add('Fayllar', 'index.php', $idx !== false, $idx === false ? 'yo‘q — Git deploy ishlamagan yoki boshqa papkaga ketgan' : "versiya: $which, o‘zgargan: " . date('Y-m-d H:i', filemtime("$root/index.php")));
foreach (['surveys.php', 'surveys.json', '.htaccess', 'a/s.html', 'a/admin.html', 'a/ds.css', 'seed/staff.json'] as $f) $add('Fayllar', $f, is_file("$root/$f"));
$add('Fayllar', '.migrated', null, is_file("$root/.migrated") ? 'bor (jadvallar yaratilgan)' : 'yo‘q (birinchi so‘rovda jadvallar yaratiladi)');
$ht = @file_get_contents("$root/.htaccess") ?: '';
$add('Fayllar', '.htaccess: PHP 8.3 handler', null, str_contains($ht, 'ea-php83') ? 'bor' : 'yo‘q — PHP versiyasi MultiPHP sozlamasiga bog‘liq (yuqoridagi “Versiya” qatoriga qarang)');
$add('Fayllar', 'hash.php', !is_file("$root/hash.php"), is_file("$root/hash.php") ? 'hali turibdi — o‘chiring!' : 'yo‘q (to‘g‘ri)');

/* ---------- config ---------- */
$cfgPath = getenv('FIKR_CONFIG') ?: '/home/azama430/fikr-config.php';
$C = null; $raw = @file_get_contents($cfgPath);
$add('Config', 'Fayl', $raw !== false, $raw === false ? "$cfgPath topilmadi yoki o‘qib bo‘lmaydi" : $cfgPath);
if ($raw !== false) {
  $add('Config', 'Boshlanishi', str_starts_with($raw, '<?php'), str_starts_with($raw, "\xEF\xBB\xBF") ? 'BOM bor — faylni BOMsiz UTF-8 saqlang (login cookie yo‘qolishi mumkin)' : (str_starts_with($raw, '<?php') ? '<?php' : '“<?php” dan oldin bo‘sh joy yoki matn bor — olib tashlang'));
  if (preg_match('/\?>\s*\S|\?>\s+$/', $raw)) $add('Config', 'Oxiri', false, '“?>” dan keyin bo‘sh qator/matn bor — “?>” ni olib tashlang');
  try { $C = (function ($p) { ob_start(); $r = require $p; ob_end_clean(); return $r; })($cfgPath); }
  catch (Throwable $e) { while (ob_get_level()) ob_end_clean(); $add('Config', 'PHP sintaksis', false, get_class($e) . ': ' . $e->getMessage() . ' (qator ' . $e->getLine() . ')'); }
  if ($C !== null) $add('Config', 'return [...]', is_array($C), is_array($C) ? 'massiv' : 'fayl massiv qaytarmayapti');
}
if (is_array($C)) {
  foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'PUBLIC_URL'] as $k) $add('Config', $k, null, var_export($C[$k] ?? null, true));
  $add('Config', 'DB_PASS', !empty($C['DB_PASS']), empty($C['DB_PASS']) ? 'bo‘sh' : 'to‘ldirilgan (' . strlen($C['DB_PASS']) . ' belgi)');
  $u = (string) ($C['ADMIN_USER'] ?? '');
  $add('Config', 'ADMIN_USER (login)', $u !== '' && $u === trim($u), $u === '' ? 'bo‘sh' : '“' . $u . '” — loginga aynan shuni yozing (katta-kichik harf farqi yo‘q)' . ($u !== trim($u) ? ' ⚠ boshida/oxirida bo‘sh joy bor — olib tashlang' : ''));
  $s = (string) ($C['SESSION_SECRET'] ?? '');
  $weak = $s !== '' && (strlen($s) < 32 || count(array_unique(str_split($s))) < 8);
  $add('Config', 'SESSION_SECRET', $s !== '' && !$weak, $s === '' ? 'bo‘sh' : strlen($s) . ' belgi' . ($weak ? ' — juda oddiy (namunadagi nollar?). Bu bilan begona odam admin bo‘lib kira oladi: yangi tasodifiy 64 belgi qo‘ying' : ''));
  $add('Config', 'COOKIE_INSECURE', empty($C['COOKIE_INSECURE']), empty($C['COOKIE_INSECURE']) ? 'false (to‘g‘ri)' : 'true — serverda false bo‘lishi kerak');

  // ADMIN_HASH: the usual reasons a right password still gets "Login yoki parol noto'g'ri"
  $hash = (string) ($C['ADMIN_HASH'] ?? '');
  $why = [];
  if ($hash === '') $why[] = 'bo‘sh';
  if (preg_match('/[\'"]ADMIN_HASH[\'"]\s*=>\s*"/', $raw)) $why[] = 'qiymat qo‘sh tirnoq "..." ichida — PHP $ belgidan keyingi qismni buzadi. Bitta tirnoq \'...\' ishlating';
  if (str_starts_with($hash, 'scrypt:') || str_starts_with($hash, 'pbkdf2:')) $why[] = 'bu Node/Cloudflare hashi — PHP uni tanimaydi, yangisini yarating';
  if (str_starts_with(ltrim($hash), 'ADMIN_HASH')) $why[] = '“ADMIN_HASH=” so‘zi ham qo‘shilib ketgan — faqat $2y$... qismini qo‘ying';
  if ($hash !== trim($hash)) $why[] = 'boshida yoki oxirida bo‘sh joy / yangi qator bor';
  if ($hash !== '' && password_get_info($hash)['algoName'] === 'unknown' && !$why) $why[] = 'password_hash() natijasiga o‘xshamaydi (uzunligi ' . strlen($hash) . '; $2y$ bilan boshlanib 60 belgi bo‘lishi kerak)';
  $add('Config', 'ADMIN_HASH', !$why, $why ? implode('; ', $why) : password_get_info($hash)['algoName'] . ', ' . strlen($hash) . ' belgi (to‘g‘ri format)');
}

/* ---------- database ---------- */
if (is_array($C) && extension_loaded('pdo_mysql')) {
  try {
    $db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', ($C['DB_HOST'] ?? '') ?: 'localhost', (int) (($C['DB_PORT'] ?? 0) ?: 3306), $C['DB_NAME'] ?? ''),
      (string) ($C['DB_USER'] ?? ''), (string) ($C['DB_PASS'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $add('Baza', 'Ulanish', true, $db->query('SELECT VERSION()')->fetchColumn() . ' · sql_mode: ' . ($db->query('SELECT @@SESSION.sql_mode')->fetchColumn() ?: '—'));
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['staff', 'response', 'hits'] as $t) {
      $has = in_array($t, $tables, true);
      $add('Baza', "Jadval $t", $has ? true : null, $has ? $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . ' qator' : 'yo‘q (sayt birinchi ochilganda yaratiladi)');
    }
    if (in_array('staff', $tables, true)) {
      $dup = (int) $db->query('SELECT COUNT(*) FROM (SELECT name FROM staff GROUP BY name, position, dept HAVING COUNT(*) > 1) x')->fetchColumn();
      $add('Baza', 'Takroriy xodimlar', $dup === 0, $dup ? "$dup ta xodim bir necha marta yozilgan (birinchi ishga tushishda ikki marta seed bo‘lgan)" : 'yo‘q');
    }
    if (in_array('hits', $tables, true)) {
      $st = $db->prepare("SELECT COUNT(*) FROM hits WHERE k LIKE 'l:%' AND n > 8 AND reset_at > ?"); $st->execute([date('Y-m-d H:i:s')]);
      $locked = (int) $st->fetchColumn();
      $add('Baza', 'Login bloklari', $locked === 0, $locked ? "$locked ta IP 15 daqiqaga bloklangan (juda ko‘p urinish) — 15 daqiqa kuting" : 'yo‘q');
    }
  } catch (Throwable $e) {
    $add('Baza', 'Ulanish', false, 'xato ' . ($e->errorInfo[1] ?? '') . ': ' . $e->getMessage());
  }
}

/* ---------- live password test (POST only, nothing stored or logged) ---------- */
$test = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_array($C)) {
  $u = (string) ($_POST['u'] ?? ''); $p = (string) ($_POST['p'] ?? '');
  $test = [
    hash_equals(mb_strtolower(trim((string) ($C['ADMIN_USER'] ?? '')), 'UTF-8'), mb_strtolower(trim($u), 'UTF-8')),
    password_verify($p, (string) ($C['ADMIN_HASH'] ?? '')),
  ];
}
$bad = count(array_filter($rows, fn($r) => $r[2] === false));
?>
<!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Fikr — tekshiruv</title>
<style>body{font:15px/1.45 system-ui,sans-serif;max-width:860px;margin:24px auto;padding:0 16px;color:#1a1a1a}h1{font-size:22px}table{border-collapse:collapse;width:100%}td{border-top:1px solid #e3e3e3;padding:6px 8px;vertical-align:top}td.g{color:#777;width:70px}td.w{width:200px;font-weight:600}.ok{color:#127a45}.no{color:#c0262d;font-weight:600}.in{color:#666}.box{padding:12px 14px;border-radius:10px;margin:14px 0}.red{background:#fdecec}.grn{background:#e8f6ee}input{font:inherit;padding:6px;width:100%;box-sizing:border-box;margin:4px 0 10px}button{font:inherit;padding:8px 16px}</style></head><body>
<h1>Fikr — server tekshiruvi</h1>
<div class="box red"><b>Tekshirib bo‘lgach, <code>check</code> papkasini darhol o‘chiring.</b></div>
<div class="box <?= $bad ? 'red' : 'grn' ?>"><?= $bad ? "$bad ta muammo topildi (qizil qatorlar)." : 'Muammo topilmadi.' ?></div>
<table><?php foreach ($rows as [$g, $w, $ok, $d]): ?>
<tr><td class="g"><?= $h($g) ?></td><td class="w"><?= $h($w) ?></td><td class="<?= $ok === true ? 'ok' : ($ok === false ? 'no' : 'in') ?>"><?= $ok === true ? '✓ ' : ($ok === false ? '✗ ' : '') ?><?= $h($d) ?></td></tr>
<?php endforeach ?></table>
<h2>Login va parolni sinash</h2>
<p>Kiritilgan parol hech qayerga saqlanmaydi, faqat config’dagi hash bilan solishtiriladi.</p>
<?php if ($test): ?><div class="box <?= $test[0] && $test[1] ? 'grn' : 'red' ?>">Login: <?= $test[0] ? '✓ to‘g‘ri' : '✗ noto‘g‘ri — config’da “' . $h($C['ADMIN_USER'] ?? '') . '”' ?><br>Parol: <?= $test[1] ? '✓ to‘g‘ri' : '✗ hash bilan mos kelmadi' ?></div><?php endif ?>
<form method="post" autocomplete="off"><label>Login<input name="u" autocapitalize="none"></label><label>Parol<input name="p" type="password"></label><button>Sinash</button></form>
</body></html>
