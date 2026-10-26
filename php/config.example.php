<?php
// Copy to /home/azama430/fikr-config.php (outside the web root) and fill in. Never commit the real file.
return [
  'DB_HOST' => 'localhost',
  'DB_PORT' => 3306,
  'DB_USER' => 'azama430_fikr',
  'DB_PASS' => '',
  'DB_NAME' => 'azama430_fikr',
  'ADMIN_USER' => 'bosh',
  'ADMIN_NAME' => '',          // greeting, e.g. 'J. Ubbiyev'; empty → 'Bosh shifokor'
  'ADMIN_HASH' => '',          // output of `php php/hash.php`; keep the single quotes ($ signs)
  'SESSION_SECRET' => '',      // `openssl rand -hex 32`; changing it logs everyone out
  'PUBLIC_URL' => 'https://fikr.endork.uz', // goes into the QR codes
  'COOKIE_INSECURE' => false,  // true only for local http
];
