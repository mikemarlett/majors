<?php

/**
 * Router for PHP's built-in server, so the app can be exercised locally
 * without the CMS:
 *
 *   php -S 127.0.0.1:8080 app/dev/router.php
 *   open http://127.0.0.1:8080/academics/majors/degree_maps/maps.php
 *
 * - DOCUMENT_ROOT is app/dev/stub-docroot (tiny stand-ins for the site's
 *   header/footer includes; put config 'docroot' there in app.local.php too).
 * - /academics/majors/** is served from docroot/academics/majors/** in the repo.
 * - Anything else is served from the stub docroot if it exists.
 */

declare(strict_types=1);

$repo    = dirname(__DIR__, 2);
$stub    = __DIR__ . '/stub-docroot';
$public  = $repo . '/docroot';
$path    = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path    = rawurldecode($path);

$_SERVER['DOCUMENT_ROOT'] = $stub;

$candidate = null;
if (str_starts_with($path, '/academics/majors/')) {
    $candidate = $public . $path;
    if (is_dir($candidate)) {
        $candidate = rtrim($candidate, '/') . '/index.php';
    }
} elseif (is_file($stub . $path)) {
    $candidate = $stub . $path;
}

if ($candidate === null || !is_file($candidate)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Not found: {$path}\n";
    return true;
}

if (str_ends_with($candidate, '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $candidate;
    $_SERVER['SCRIPT_NAME']     = $path;
    $_SERVER['PHP_SELF']        = $path;
    chdir(dirname($candidate));
    require $candidate;
    return true;
}

// Static file.
$types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
    'ico' => 'image/x-icon', 'json' => 'application/json', 'html' => 'text/html', 'inc' => 'text/plain', 'ounav' => 'text/plain'];
$ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
readfile($candidate);
return true;
