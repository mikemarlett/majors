<?php

/**
 * Application bootstrap. Front controllers under docroot/academics/majors
 * require this file (via _bootstrap.php next to them) and receive a Kernel.
 *
 *   $app = require '/data/www/config/majors/bootstrap.php';
 *   $app->run(\Majors\Http\PublicMapsController::class);
 *
 * $MAJORS_WEB_ROOT may be set by the caller to the deployed public folder
 * (docroot/academics/majors); it defaults to the repo layout.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Support/Autoloader.php';
\Majors\Support\Autoloader::register('Majors\\', __DIR__ . '/src');

$config  = \Majors\Support\Config::load(__DIR__ . '/config');
$webRoot = isset($MAJORS_WEB_ROOT) && is_string($MAJORS_WEB_ROOT)
    ? $MAJORS_WEB_ROOT
    : dirname(__DIR__) . '/docroot/academics/majors';

return new \Majors\Kernel($config, __DIR__, $webRoot);
