<?php
// php php/test.php — mirrors app/grade.test.js. Exits 1 on any failure.
require __DIR__ . '/surveys.php';
$fail = 0;
function eq($got, $want, string $name): void {
  global $fail;
  $g = json_encode($got, JSON_UNESCAPED_UNICODE); $w = json_encode($want, JSON_UNESCAPED_UNICODE);
  if ($g === $w) { echo "ok   $name\n"; return; }
  $fail++; echo "FAIL $name\n  got  $g\n  want $w\n";
}

$full = ['attention' => 5, 'explain' => 4, 'polite' => 3, 'time' => 'yes', 'supplement' => 'yes', 'nps' => 9];
eq(grade('doctor', $full), ['answers' => $full, 'score' => 4, 'nps' => 9, 'flags' => 1], 'doctor: score is the 1–5 mean, nps kept, supplement=yes is a flag');
eq(grade('doctor', ['nps' => null] + $full), null, 'missing nps rejected');
eq(grade('doctor', ['polite' => 6] + $full), null, 'out-of-range rejected');
eq(grade('doctor', ['time' => 'maybe'] + $full), null, 'foreign option rejected');
eq(grade('doctor', ['polite' => '3'] + $full), null, 'string number rejected (JS Number.isInteger)');
eq(grade('doctor', ['polite' => 3.0] + $full)['answers']['polite'] ?? null, 3, '3.0 counts as integer, stored as 3');
eq(grade('constructor', $full), null, 'unknown template rejected');
eq(grade('doctor', null), null, 'null answers rejected');
eq(grade('doctor', ['attention' => 5, 'explain' => 4, 'polite' => 4] + $full)['score'], 4.33, 'score rounds to 2 decimals');
$a = ['polite' => 5, 'speed' => 5, 'accuracy' => 5, 'explain' => 5, 'receipt' => 'no', 'upsell' => 'yes', 'junk' => 1];
eq(grade('cashier', $a), ['answers' => ['polite' => 5, 'speed' => 5, 'accuracy' => 5, 'explain' => 5, 'receipt' => 'no', 'upsell' => 'yes'], 'score' => 5, 'nps' => null, 'flags' => 2], 'cashier: extra keys dropped, no nps, two flags');

$missing = [];
foreach (SURVEYS as $k => $s) {
  if (count(array_unique(array_column($s['q'], 'id'))) !== count($s['q'])) $missing[] = "$k dup ids";
  foreach (LANGS as $l) {
    if (empty($s['label'][$l])) $missing[] = "$k.label.$l";
    foreach ($s['q'] as $q) { if (empty($q[$l])) $missing[] = "$k.{$q['id']}.$l"; foreach ($q['opts'] ?? [] as $o) if (empty(OPT[$o][$l])) $missing[] = "$o.$l"; }
  }
}
eq($missing, [], 'every template has unique ids and text in every language');

$chrome = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Mobile Safari/537.36';
eq(device_info($chrome, ['model' => 'SM-A515F', 'platformVersion' => '13.0.0', 'w' => 412, 'h' => 915]), ['model' => 'Samsung SM-A515F', 'os' => 'Android 13.0.0', 'screen' => '412×915'], 'device: client-hint model wins');
eq(device_info($chrome, []), ['model' => 'Android', 'os' => 'Android 10', 'screen' => ''], 'device: reduced UA → Android');
eq(device_info('Mozilla/5.0 (Linux; Android 9; Redmi Note 8 Build/PKQ1) AppleWebKit/537.36', [])['model'], 'Redmi Note 8', 'device: old UA model, Build stripped');
$ios = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15';
eq(device_info($ios, ['w' => 390, 'h' => 844, 'model' => '<script>']), ['model' => 'script', 'os' => 'iOS 17.5', 'screen' => '390×844'], 'device: iPhone keeps screen, model sanitised');
eq(device_info('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', []), ['model' => 'Kompyuter', 'os' => 'Windows', 'screen' => ''], 'device: desktop');
eq(device_info('', []), ['model' => 'Nomaʼlum', 'os' => '', 'screen' => ''], 'device: unknown');

eq(clean_phone(''), null, 'phone: blank → null');
eq(clean_phone(null), null, 'phone: missing → null');
eq(clean_phone('90 123 45 67'), '+998901234567', 'phone: 9 digits');
eq(clean_phone('+998 (90) 123-45-67'), '+998901234567', 'phone: +998 form');
eq(clean_phone('90 123 45'), false, 'phone: short → invalid');
eq(clean_phone('+7 912 345 67 89'), false, 'phone: foreign → invalid');

eq([js_round(-12.5), js_round(12.5), js_round(2.4999)], [-12, 13, 2], 'js_round matches Math.round');
eq([cut('ʻOʻ😀x', 3), jtrim("\u{A0} a \u{FEFF}")], ['ʻOʻ', 'a'], 'cut by characters, JS-style trim');

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit($fail ? 1 : 0);
