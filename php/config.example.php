<?php
// Copy to /home/azama430/fikr-config.php (OUTSIDE public_html) and chmod 600. Never commit the real file.
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_USER' => 'azama430_fikr',          // cPanel → MySQL Databases (user must have ALL PRIVILEGES on the DB)
    'DB_PASS' => 'change-me',              // the password set for that MySQL user
    'DB_NAME' => 'azama430_fikr',          // cPanel → MySQL Databases
    'ADMIN_USER' => 'admin',               // admin panel login
    'ADMIN_NAME' => 'Administrator',       // name shown in the panel
    'ADMIN_HASH' => '$2y$10$replace.with.output.of.hash.php', // the ADMIN_HASH=... line printed by hash.php
    'SESSION_SECRET' => '0000000000000000000000000000000000000000000000000000000000000000', // 64 hex chars: php -r 'echo bin2hex(random_bytes(32));'
    'PUBLIC_URL' => 'https://fikr.endork.uz', // base URL put into QR codes, no trailing slash
    'COOKIE_INSECURE' => false,            // true only for local plain-http testing
];
