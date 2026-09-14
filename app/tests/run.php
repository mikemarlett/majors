<?php

/**
 * Test runner: php -l over every PHP file, then each tests/*.php script.
 *   php app/tests/run.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fail = 0;

echo "== php -l\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || str_contains($p, '/.git/') || str_contains($p, '/data/main/')) {
        continue;
    }
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $fail++;
        echo implode("\n", $out) . "\n";
    }
    $out = [];
}
echo $fail === 0 ? "  all files parse\n" : "  {$fail} file(s) with syntax errors\n";

echo "== scripts\n";
foreach (glob(__DIR__ . '/*.php') ?: [] as $script) {
    $name = basename($script);
    if (in_array($name, ['run.php', 'bootstrap.php', 'parity_legacy.php'], true)) { // parity needs the legacy file + DB; see its header
        continue;
    }
    echo "-- {$name}\n";
    passthru('php ' . escapeshellarg($script), $code);
    if ($code !== 0) {
        $fail++;
    }
}
exit($fail === 0 ? 0 : 1);
