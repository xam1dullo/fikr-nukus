<?php
// Run locally: php php/hash.php → type the password → paste the printed line into the config. Not deployed.
PHP_SAPI === 'cli' || exit;
fwrite(STDOUT, 'Parol: ');
$pass = trim((string) fgets(STDIN));
if (strlen($pass) < 10) { fwrite(STDERR, "Kamida 10 belgi.\n"); exit(1); }
echo "\n'ADMIN_HASH' => '" . password_hash($pass, PASSWORD_DEFAULT) . "',\n";
