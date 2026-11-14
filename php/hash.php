<?php
// Prints ADMIN_HASH=... for fikr-config.php. CLI: `php hash.php`. Web: upload temporarily, open /hash.php, then DELETE the file.
if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, 'Parol: ');
    $tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
    $stty = $tty && function_exists('shell_exec') && trim((string)@shell_exec('command -v stty 2>/dev/null')) !== '';
    if ($stty) shell_exec('stty -echo');
    $p = rtrim((string)fgets(STDIN), "\r\n");
    if ($stty) { shell_exec('stty echo'); fwrite(STDOUT, "\n"); }
    if (preg_match_all('/./su', $p) < 10) { fwrite(STDERR, "Kamida 10 belgi.\n"); exit(1); }
    fwrite(STDOUT, 'ADMIN_HASH=' . password_hash($p, PASSWORD_DEFAULT) . "\n");
    exit(0);
}

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$out = $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $p = (string)($_POST['p'] ?? '');
    if (preg_match_all('/./su', $p) < 10) $err = 'Kamida 10 belgi.';
    else $out = 'ADMIN_HASH=' . password_hash($p, PASSWORD_DEFAULT);
    unset($p, $_POST['p']);
}
$h = fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Parol xeshi</title></head>
<body style="font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem">
<h1 style="font-size:1.3rem">Admin parol xeshi</h1>
<p style="color:#b00020;font-weight:700">Faylni darhol o'chiring: ishingiz tugashi bilan hash.php ni serverdan o'chirib tashlang.</p>
<?php if ($out): ?>
<p>Bu qatorni <code>fikr-config.php</code> dagi <code>ADMIN_HASH</code> ga qo'ying:</p>
<pre style="background:#f3f3f3;padding:.75rem;white-space:pre-wrap;word-break:break-all"><?= $h($out) ?></pre>
<?php endif; ?>
<?php if ($err): ?><p style="color:#b00020"><?= $h($err) ?></p><?php endif; ?>
<form method="post" autocomplete="off">
<label>Parol (kamida 10 belgi)<br>
<input type="password" name="p" minlength="10" required autocomplete="new-password" style="font:inherit;padding:.4rem;width:100%;box-sizing:border-box"></label>
<p><button type="submit" style="font:inherit;padding:.4rem 1rem">Xeshlash</button></p>
</form>
<p style="color:#555">Parol saqlanmaydi va hech qayerga yozilmaydi.</p>
</body></html>
