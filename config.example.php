<?php
/**
 * Copy this file to config.php and fill in your real values:
 *
 *   cp config.example.php config.php
 *
 * config.php is gitignored - never commit real credentials.
 */

return [
    'scanDir'     => $_SERVER['HOME'] . '/public_html',
    'gitleaksBin' => __DIR__ . '/bin/gitleaks',
    'configFile'  => __DIR__ . '/.config.toml',
    'reportDir'   => __DIR__ . '/logs',

    'smtp' => [
        'host'     => 'smtp.yourprovider.com',
        'port'     => 587,
        'user'     => 'your_smtp_username',
        'pass'     => 'your_smtp_password',
        'from'     => 'alerts@yourdomain.com',
        'fromName' => 'Gitleaks Alert',
        'to'       => 'you@example.com',
    ],
];
