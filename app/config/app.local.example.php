<?php

/**
 * Box-wide overrides: copy to app.local.php (gitignored). Applies to every
 * site served from this app root; per-site differences go in
 * app.<site>.php (see app.www-dev.example.php / app.www-test.example.php).
 *
 * On the WSU servers this file is usually EMPTY or absent: the defaults in
 * app.php already point at /data/www/config/functions.php and the phpCAS
 * config, which both www-dev and www-test share.
 *
 * The block below is the local sandbox (no CMS, no CAS).
 */

declare(strict_types=1);

return [
    'env'    => 'dev',
    'design' => 'old',

    // The dev router serves app/dev/stub-docroot as the docroot.
    'docroot' => dirname(__DIR__) . '/dev/stub-docroot',

    'db' => [
        'driver' => 'dsn',
        'host'   => '127.0.0.1',
        'name'   => 'formshandlerdb',
        'user'   => 'CHANGE_ME',
        'pass'   => 'CHANGE_ME',
    ],

    'auth' => [
        'provider'    => 'dev',
        'dev_default' => 'mike.marlett@wichita.edu',
    ],

    'session' => [
        'secure' => false,
    ],
];
