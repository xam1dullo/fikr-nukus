<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/surveys.php';

$fails = 0;
function ok(string $name, bool $cond): void {
  global $fails;
  echo ($cond ? 'PASS' : 'FAIL') . " $name\n";
  if (!$cond) $fails++;
}
function same(string $name, $got, $want): void {
  ok($name, $got === $want);
  if ($got !== $want) echo '  got:  ' . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n";
}

['SURVEYS' => $SURVEYS, 'OPT' => $OPT, 'LANGS' => $LANGS] = surveys();
$full = ['attention' => 5, 'explain' => 4, 'polite' => 3, 'time' => 'yes', 'supplement' => 'yes', 'nps' => 9];

same('doctor: score mean, nps kept, supplement=yes flag', grade('doctor', $full), ['answers' => $full, 'score' => 4, 'nps' => 9, 'flags' => 1]);
ok('score whole number is int', is_int(grade('doctor', $full)['score']));
same('score json is 4 not 4.0', json_encode(grade('doctor', $full)['score']), '4');

$nps = $full; unset($nps['nps']);
same('reject missing nps', grade('doctor', $nps), null);
same('reject null nps', grade('doctor', ['nps' => null] + $full), null);
same('reject polite 6', grade('doctor', ['polite' => 6] + $full), null);
same('reject time maybe', grade('doctor', ['time' => 'maybe'] + $full), null);
same('reject unknown key', grade('constructor', $full), null);
same('reject null raw', grade('doctor', null), null);
same('reject string scale', grade('doctor', ['polite' => '3'] + $full), null);
same('reject bool scale', grade('doctor', ['polite' => true] + $full), null);
same('reject 4.5 scale', grade('doctor', ['polite' => 4.5] + $full), null);
same('reject opt loose match', grade('doctor', ['time' => true] + $full), null);

$g = grade('doctor', ['attention' => 5.0] + $full);
ok('whole float 5.0 accepted as scale answer', $g !== null && $g['answers']['attention'] === 5);

$a = ['polite' => 5, 'speed' => 5, 'accuracy' => 5, 'explain' => 5, 'receipt' => 'no', 'upsell' => 'yes', 'junk' => 1];
same('cashier: extra keys dropped, no nps, two flags', grade('cashier', $a),
  ['answers' => ['polite' => 5, 'speed' => 5, 'accuracy' => 5, 'explain' => 5, 'receipt' => 'no', 'upsell' => 'yes'], 'score' => 5, 'nps' => null, 'flags' => 2]);

$bad = [];
foreach ($SURVEYS as $k => $s) {
  $ids = array_column($s['q'], 'id');
  if (count(array_unique($ids)) !== count($ids)) $bad[] = "$k ids";
  foreach ($LANGS as $l) {
    if (empty($s['label'][$l])) $bad[] = "$k.label.$l";
    foreach ($s['q'] as $q) {
      if (empty($q[$l])) $bad[] = "$k.{$q['id']}.$l";
      foreach ($q['opts'] ?? [] as $o) if (empty($OPT[$o][$l])) $bad[] = "$o.$l";
    }
  }
}
same('every template has unique ids and text in every language', $bad, []);
ok('surveys loaded (' . count($SURVEYS) . ' templates, ' . count($LANGS) . ' langs)', count($SURVEYS) > 0 && count($LANGS) === 4);

$x = "\u{00D7}";
$chrome = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Mobile Safari/537.36';
same('device: client-hint model wins', deviceInfo($chrome, ['model' => 'SM-A515F', 'platformVersion' => '13.0.0', 'w' => 412, 'h' => 915]),
  ['model' => 'Samsung SM-A515F', 'os' => 'Android 13.0.0', 'screen' => "412{$x}915"]);
same('device: reduced UA K', deviceInfo($chrome, []), ['model' => 'Android', 'os' => 'Android 10', 'screen' => '']);
$old = 'Mozilla/5.0 (Linux; Android 9; Redmi Note 8 Build/PKQ1) AppleWebKit/537.36';
same('device: Build/ stripped', deviceInfo($old, [])['model'], 'Redmi Note 8');
$ios = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15';
same('device: iPhone keeps screen, model sanitised', deviceInfo($ios, ['w' => 390, 'h' => 844, 'model' => '<script>']), ['model' => 'script', 'os' => 'iOS 17.5', 'screen' => "390{$x}844"]);
same('device: multibyte stripped', deviceInfo($chrome, ['model' => 'Галакси A5'])['model'], 'A5');
same('device: whole float screen', deviceInfo($ios, ['w' => 390.0, 'h' => 844])['screen'], "390{$x}844");
same('device: screen >= 10000 dropped', deviceInfo($ios, ['w' => 10000, 'h' => 844])['screen'], '');
same('device: Windows', deviceInfo('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', []), ['model' => 'Kompyuter', 'os' => 'Windows', 'screen' => '']);
same('device: macOS', deviceInfo('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', [])['os'], 'macOS');
same('device: Linux', deviceInfo('Mozilla/5.0 (X11; Linux x86_64)', [])['os'], 'Linux');
same('device: unknown', deviceInfo('', []), ['model' => "Noma\u{02BC}lum", 'os' => '', 'screen' => '']);
same('device: Xiaomi prefix', deviceInfo($chrome, ['model' => '2201117TG'])['model'], 'Xiaomi 2201117TG');

same('phone: empty is null', cleanPhone(''), null);
same('phone: null is null', cleanPhone(null), null);
same('phone: 9 digits', cleanPhone('90 123 45 67'), '+998901234567');
same('phone: +998 form', cleanPhone('+998 (90) 123-45-67'), '+998901234567');
same('phone: too short invalid', cleanPhone('90 123 45'), false);
same('phone: foreign invalid', cleanPhone('+7 912 345 67 89'), false);
same('phone: int', cleanPhone(901234567), '+998901234567');
same('phone: bool true is blank', cleanPhone(true), null);

same('jsRound(-2.5) === -2', jsRound(-2.5), -2);
same('jsRound(2.5) === 3', jsRound(2.5), 3);
same('num(4.0) === 4', num(4.0), 4);
same('num(4.25) === 4.25', num(4.25), 4.25);
same('num(null) === null', num(null), null);

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
