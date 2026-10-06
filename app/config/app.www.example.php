<?php

/**
 * www.wichita.edu (production box). Copy to app.www.php there.
 *
 * Photos are published to www itself, so there is no image_base. Sign-in uses
 * Azure (Entra ID), as on www-test: add
 *   https://www.wichita.edu/academics/majors/auth/login.php
 * to the app registration's redirect URIs, and make sure the box has
 * /data/www/config/phpAzure/loader.php. Use 'cas' instead once ITS registers
 * that URL on cas.wichita.edu.
 */

declare(strict_types=1);

return [
    'design' => 'old',
    'auth'   => ['provider' => 'azure', 'service_host' => 'www.wichita.edu'],
    // At the Majors switch, once the CMS program pages are retired:
    // 'majors' => ['cms_import' => false],
];
