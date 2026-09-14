<?php

/**
 * Shared prelude for every front controller in this folder tree.
 *
 * Finds the application root — approot.php next to this file on a server
 * (<?php return '/data/www/config/majors';) or ../../../app in the git
 * checkout — and exposes $app (Majors\Kernel).
 */

declare(strict_types=1);

$MAJORS_WEB_ROOT = __DIR__;
$approotFile     = __DIR__ . '/approot.php';
$approot         = is_file($approotFile) ? require $approotFile : dirname(__DIR__, 3) . '/app';

if (!is_string($approot) || !is_file($approot . '/bootstrap.php')) {
    http_response_code(500);
    exit('Application root not found. Create approot.php next to _bootstrap.php.');
}

/** @var \Majors\Kernel $app */
$app = require $approot . '/bootstrap.php';
