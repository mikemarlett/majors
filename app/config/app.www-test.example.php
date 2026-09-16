<?php

/**
 * www-test only (docroot /data/www/main-test): copy to app.www-test.php.
 * Selected automatically when the request host is www-test.wichita.edu, or
 * for CLI scripts with MAJORS_SITE=www-test.
 *
 * www-test is where advisors edit; it mirrors production, so normally
 * nothing needs overriding here.
 */

declare(strict_types=1);

return [
    'design' => 'old',
    'auth'   => ['service_host' => 'www-test.wichita.edu'],
];
