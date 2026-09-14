<?php

/** Shared test prelude: autoloader + a Layout wired to the stub docroot. */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Support/Autoloader.php';
\Majors\Support\Autoloader::register('Majors\\', dirname(__DIR__) . '/src');

function test_layout(string $design = 'old'): \Majors\View\Layout
{
    $app = dirname(__DIR__);
    return new \Majors\View\Layout(
        $app . '/templates',
        $design,
        new \Majors\View\Theme($app . '/dev/stub-docroot/_resources/' . ($design === 'new' ? '_theme/includes' : 'includes')),
        '/academics/majors',
        dirname($app) . '/docroot/academics/majors/assets',
        ['site_name' => 'Test', 'logo' => '/logo.svg', 'sprite' => '/sprite.svg'],
    );
}

$GLOBALS['__failures'] = 0;
function check(bool $ok, string $what): void
{
    echo($ok ? '  ok   ' : '  FAIL ') . $what . PHP_EOL;
    if (!$ok) {
        $GLOBALS['__failures']++;
    }
}
function finish(): never
{
    $n = (int) $GLOBALS['__failures'];
    echo $n === 0 ? "All checks passed.\n" : "{$n} check(s) FAILED.\n";
    exit($n === 0 ? 0 : 1);
}
