<?php

/**
 * Base configuration. Do not put server specifics here: copy
 * app.local.example.php to app.local.php (gitignored) and override there.
 */

declare(strict_types=1);

return [
    // 'production' | 'dev'. Only 'dev' allows the DevProvider sign-in and
    // shows PHP errors in the browser.
    'env' => 'production',

    // Which page chrome + template set to use: 'old' (current wichita.edu
    // design, includes at _resources/includes) or 'new' (NewCity/Tailwind
    // design on www-dev, includes at _resources/_theme/includes).
    'design' => 'old',

    // Public URL of docroot/academics/majors on this server.
    'base_url' => '/academics/majors',

    // Web docroot. null = $_SERVER['DOCUMENT_ROOT'].
    'docroot' => null,

    // Directory with the site's header/footer fragments, per design.
    // Relative paths are resolved against the docroot.
    'theme_dirs' => [
        'old' => '_resources/includes',
        'new' => '_resources/_theme/includes',
    ],

    'db' => [
        // 'site' = include the shared /data/www/config/functions.php (servers)
        // 'dsn'  = explicit host/user/pass/name (local development)
        'driver'         => 'site',
        'site_functions' => '/data/www/config/functions.php',
        'host'           => '127.0.0.1',
        'port'           => 3306,
        'socket'         => '',
        'name'           => 'formshandlerdb',
        'user'           => '',
        'pass'           => '',
    ],

    'auth' => [
        // 'cas' (WSU single sign-on via phpCAS) | 'dev' (local only, env=dev)
        'provider'    => 'cas',
        'cas_config'  => '/data/www/config/phpCAS/config.php',
        // DevProvider: email to sign in as when ?as= is not given.
        'dev_default' => null,
    ],

    'session' => [
        'name'   => 'wsumajors',
        // Must cover auth/, degree_maps/admin/ and _admin/ under base_url.
        'path'   => '/academics/majors',
        // null = Secure flag follows the request scheme.
        'secure' => null,
        'lifetime_minutes' => 480,
    ],

    // Colleges get renamed but the maps keep the name they were created with.
    // Map legacy or current spellings onto the row in majors_colleges so
    // advisor scoping still matches. key = name as found in degree_maps /
    // programs, value = the name in majors_colleges.
    'college_aliases' => [
        'College of Applied Studies' => 'College of Education',
    ],

    'site' => [
        'site_name' => 'Wichita State University',
        'logo'      => '/_resources/images/logo-blacktype.svg',
        'sprite'    => '/_resources/images/sprites/svg-sprite-custom-symbol.svg',
        // Shown on the "not on the access list" page.
        'contact'   => 'mike.marlett@wichita.edu',
        // Section nav file published by the CMS next to the degree maps.
        'degree_maps_nav' => 'academics/majors/degree_maps/_nav.ounav',
    ],
];
