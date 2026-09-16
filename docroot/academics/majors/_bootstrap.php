<?php

/**
 * Shared prelude for every front controller in this folder tree.
 *
 * Finds the application root and exposes $app (Majors\Kernel). In order:
 *   1. approot.php next to this file (<?php return '/path/to/app';) — only
 *      needed when the app lives somewhere unusual;
 *   2. /data/www/config/majors — where it is deployed on every WSU server;
 *   3. ../../../app — the git checkout, for local development.
 * Nothing has to be written into the docroot on a server.
 */

declare(strict_types=1);

$MAJORS_WEB_ROOT = __DIR__;
$approotFile     = __DIR__ . '/approot.php';
$candidates      = [];
if (is_file($approotFile)) {
    $candidates[] = require $approotFile;
}
$candidates[] = '/data/www/config/majors';
$candidates[] = dirname(__DIR__, 3) . '/app';

$approot = null;
foreach ($candidates as $candidate) {
    if (is_string($candidate) && is_file($candidate . '/bootstrap.php')) {
        $approot = $candidate;
        break;
    }
}
if ($approot === null) {
    http_response_code(500);
    exit('Application root not found: deploy app/ to /data/www/config/majors or create approot.php next to _bootstrap.php.');
}

/** @var \Majors\Kernel $app */
$app = require $approot . '/bootstrap.php';
