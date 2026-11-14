<?php
declare(strict_types=1);

function surveys(): array {
  static $s = null;
  return $s ??= json_decode(file_get_contents(__DIR__ . '/surveys.json'), true, 512, JSON_THROW_ON_ERROR);
}

// JS Math.round: halves go up, also for negatives (php round(-2.5) is -3).
function jsRound(float $x): int { return (int)floor($x + 0.5); }

function num($x) { return is_float($x) && floor($x) == $x && abs($x) < 2 ** 53 ? (int)$x : $x; }

// JS Number.isInteger: whole floats count, strings/bools don't.
function isInt($v): bool { return is_int($v) || (is_float($v) && is_finite($v) && floor($v) == $v); }

// JS String(v) for JSON-decoded values.
function jsString($v): string {
  if ($v === null) return '';
  if (is_bool($v)) return $v ? 'true' : 'false';
  if (is_float($v)) return (string)num($v);
  if (is_array($v)) return !$v || array_is_list($v) ? implode(',', array_map('jsString', $v)) : '[object Object]';
  return (string)$v;
}

function grade(string $key, mixed $raw): ?array {
  $s = surveys()['SURVEYS'][$key] ?? null;
  if (!$s || !is_array($raw)) return null;
  $answers = [];
  $sum = 0; $n = 0; $flags = 0; $nps = null;
  foreach ($s['q'] as $q) {
    $v = $raw[$q['id']] ?? null;
    if ($q['t'] === 'scale') { if (!isInt($v) || $v < 1 || $v > 5) return null; $v = (int)$v; $sum += $v; $n++; }
    elseif ($q['t'] === 'nps') { if (!isInt($v) || $v < 0 || $v > 10) return null; $v = (int)$v; $nps = $v; }
    elseif (!in_array($v, $q['opts'], true)) return null;
    if (!empty($q['bad']) && $v === $q['bad']) $flags++;
    $answers[$q['id']] = $v;
  }
  return ['answers' => $answers, 'score' => $n ? num(jsRound($sum / $n * 100) / 100) : null, 'nps' => $nps, 'flags' => $flags];
}

function deviceInfo(string $ua, array $dev): array {
  // no /u: JS \w is ASCII, so each byte of a multibyte char is stripped
  $s = fn($v, $n) => substr(trim(preg_replace('~[^\w .,()\-/+]~', '', jsString(in_array($v, [null, false, 0, 0.0, ''], true) ? '' : $v))), 0, $n);
  $model = $s($dev['model'] ?? null, 60); $os = '';
  $android = preg_match('~Android ([\d.]+)(?:; ([^;)]+))?~', $ua, $am);
  $ios = preg_match('~(iPhone|iPad|iPod).*? OS (\d+)_(\d+)~', $ua, $im);
  if ($android) {
    $os = 'Android ' . (($pv = $s($dev['platformVersion'] ?? null, 12)) !== '' ? $pv : $am[1]);
    if ($model === '' && isset($am[2]) && $am[2] !== '' && trim($am[2]) !== 'K') $model = $s(preg_replace('~ Build/.*~', '', $am[2]), 60);
  } elseif ($ios) { $os = "iOS $im[2].$im[3]"; if ($model === '') $model = $im[1]; }
  elseif (str_contains($ua, 'Windows')) $os = 'Windows';
  elseif (str_contains($ua, 'Mac OS X')) $os = 'macOS';
  elseif (str_contains($ua, 'Linux')) $os = 'Linux';
  foreach (['~^SM-~' => 'Samsung', '~^CPH~' => 'Oppo', '~^RMX~' => 'Realme', '~^V\d{4}~' => 'Vivo', '~^(M\d{4}|2\d{5,})~' => 'Xiaomi'] as $re => $b)
    if (preg_match($re, $model)) { $model = "$b $model"; break; }
  $w = $dev['w'] ?? null; $h = $dev['h'] ?? null;
  $w = isInt($w) && $w < 10000 ? num($w) : 0;
  $h = isInt($h) && $h < 10000 ? num($h) : 0;
  return [
    'model' => $model !== '' ? $model : ($android ? 'Android' : ($os !== '' ? 'Kompyuter' : "Noma\u{02BC}lum")),
    'os' => $os,
    'screen' => $w && $h ? "$w\u{00D7}$h" : '',
  ];
}

// null = blank, false = invalid (JS undefined)
function cleanPhone(mixed $v): string|null|false {
  $d = preg_replace('~\D~', '', jsString($v));
  if ($d === '') return null;
  $n = strlen($d) === 12 && str_starts_with($d, '998') ? substr($d, 3) : $d;
  return preg_match('~^\d{9}$~', $n) ? '+998' . $n : false;
}
