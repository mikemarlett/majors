<?php

/**
 * Which files in docroot/academics/majors/_images are referenced by the
 * database (any image URL in the programs, their sections, the shared blocks
 * and the legacy flat rows, including the Similar Programs background photos)?
 * Writes a manifest and, with --archive <dir>, moves the unreferenced files
 * out of the docroot.
 *
 *   php app/bin/images-audit.php                                    report only (repo checkout)
 *   php bin/images-audit.php --images /data/www/main/academics/majors/_images --archive /data/majors-images-archive
 */

declare(strict_types=1);

$app    = require dirname(__DIR__) . '/bootstrap.php';
$db     = $app->db();
// _images lives in the docroot: the git checkout's copy when run from the repo,
// otherwise the deployed folder (override with --images <dir>).
$imgDir = dirname(__DIR__, 2) . '/docroot/academics/majors/_images';
if (($i = array_search('--images', $argv, true)) !== false) {
    $imgDir = $argv[$i + 1] ?? '';
} elseif (!is_dir($imgDir)) {
    $imgDir = $app->docroot() . '/academics/majors/_images';
}
if (!is_dir($imgDir)) {
    fwrite(STDERR, "_images not found at {$imgDir}; pass --images <dir>\n");
    exit(1);
}
$archiveTo = null;
if (($i = array_search('--archive', $argv, true)) !== false) {
    $archiveTo = $argv[$i + 1] ?? null;
    if ($archiveTo === null) {
        fwrite(STDERR, "--archive needs a directory\n");
        exit(1);
    }
}

// Every string column may hold an image URL (the copy is HTML): the legacy flat rows, the programs
// (photo, Similar Programs background, note…), their sections and the shared blocks.
// File names may contain spaces ("transition to teaching program.jpg"): a bare address is read whole,
// a quoted HTML attribute up to its closing quote, and only loose text stops at the first space.
$referenced = [];
$add = static function (string $path) use (&$referenced): void {
    $path = trim((string) preg_replace('/[?#].*$/s', '', $path));
    if ($path !== '') {
        $referenced[rawurldecode($path)] = true;
    }
};
foreach (['majors_programs_content', 'majors_academic_programs', 'majors_program_sections', 'majors_content_blocks'] as $table) {
    if ($db->query("SHOW TABLES LIKE '{$table}'")->num_rows === 0) {
        continue;
    }
    $res = $db->query("SELECT * FROM `{$table}`");
    while ($row = $res->fetch_assoc()) {
        foreach ($row as $v) {
            if (!is_string($v) || ($v = trim($v)) === '') {
                continue;
            }
            if (strpbrk($v, "<>\"'\n") === false && preg_match('#^(?:https?://[^/\s]+)?/academics/majors/_images/(.+)$#i', $v, $m)) {
                $add($m[1]);                                   // image_url, similar_bg_url…: the whole value
                continue;
            }
            if (preg_match_all('#=\s*(["\'])(?:https?://[^/"\']+)?/academics/majors/_images/([^"\']+?)\1#i', $v, $m)) {
                foreach ($m[2] as $f) {
                    $add($f);                                  // src="…" / href='…'
                }
            }
            $loose = (string) preg_replace('#=\s*(["\']).*?\1#s', '=""', $v);
            if (preg_match_all('#(?:https?://[^/\s"\']+)?/academics/majors/_images/([^\s"\'<>?]+)#i', $loose, $m)) {
                foreach ($m[1] as $f) {
                    $add($f);                                  // an address in plain text
                }
            }
        }
    }
}

$files = [];
if (is_dir($imgDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgDir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $files[substr($f->getPathname(), strlen($imgDir) + 1)] = $f->getSize();
    }
}
ksort($files);

$used = $orphans = [];
$usedBytes = $orphanBytes = 0;
foreach ($files as $rel => $size) {
    if (isset($referenced[$rel])) {
        $used[] = $rel;
        $usedBytes += $size;
    } else {
        $orphans[] = $rel;
        $orphanBytes += $size;
    }
}
$missing = array_diff(array_keys($referenced), array_keys($files));

$docs     = dirname(__DIR__, 2) . '/docs';
$manifest = (is_dir($docs) && is_writable($docs) ? $docs : getcwd()) . '/images-manifest.txt';
$out = "# _images audit " . date('c') . "\n# referenced: " . count($used) . " files (" . round($usedBytes / 1048576, 1) . " MB)"
    . "\n# unreferenced: " . count($orphans) . " files (" . round($orphanBytes / 1048576, 1) . " MB)"
    . "\n# referenced but missing on disk: " . count($missing) . "\n\n[referenced]\n" . implode("\n", $used)
    . "\n\n[unreferenced]\n" . implode("\n", $orphans) . "\n\n[missing]\n" . implode("\n", $missing) . "\n";
file_put_contents($manifest, $out);
echo "referenced: " . count($used) . "  unreferenced: " . count($orphans) . " (" . round($orphanBytes / 1048576, 1) . " MB)  missing: " . count($missing) . "\n";
echo "manifest: {$manifest}\n";

if ($archiveTo !== null) {
    if (!is_dir($archiveTo) && !mkdir($archiveTo, 0775, true)) {
        fwrite(STDERR, "cannot create {$archiveTo}\n");
        exit(1);
    }
    foreach ($orphans as $rel) {
        $dest = $archiveTo . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        rename($imgDir . '/' . $rel, $dest);
    }
    echo "moved " . count($orphans) . " unreferenced files to {$archiveTo}\n";
}
