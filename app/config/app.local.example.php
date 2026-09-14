<?php

/**
 * Per-server overrides. Copy to app.local.php (gitignored) and edit.
 *
 * www / www-test (production shape): usually nothing but 'design' is needed,
 * because the defaults already point at /data/www/config/functions.php and
 * the phpCAS config.
 *
 * www-dev (new design): ['design' => 'new']
 *
 * Local sandbox (no CMS, no CAS): the block below.
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
