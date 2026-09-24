<?php

/**
 * www-dev only (docroot /data/www/main-dev): copy to app.www-dev.php.
 * Selected automatically when the request host is www-dev.wichita.edu, or
 * for CLI scripts with MAJORS_SITE=www-dev.
 */

declare(strict_types=1);

return [
    'site'   => ['image_base' => 'https://www.wichita.edu'],
    'auth'   => ['service_host' => 'www-dev.wichita.edu'],
    // The new NewCity/Tailwind design lives here: chrome from _resources/_theme/includes.
    'design' => 'new',

    // Keep CAS. 'env' stays production so the dev sign-in bypass is never
    // available on a server; set 'env' => 'dev' only to see PHP errors in the browser.
];
