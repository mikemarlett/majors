<?php

/**
 * Which files in docroot/academics/majors/_images are referenced by the
 * database (majors_programs_content image URLs and any image URL inside the
 * marketing copy)? Writes a manifest and, with --archive <dir>, moves the
 * unreferenced files out of the docroot.
 *
 *   php bin/images-audit.php                       report only
 *   php bin/images-audit.php --archive /data/majors-images-archive
 */

declare(strict_types=1);

$app    = require dirname(__DIR__) . '/app/bootstrap.php';
$db     = $app->db();
$imgDir = dirname(__DIR__) . '/docroot/academics/majors/_images';
$archiveTo = null;
if (($i = array_search('--archive', $argv, true)) !== false) {
    $archiveTo = $argv[$i + 1] ?? null;
    if ($archiveTo === null) {
        fwrite(STDERR, "--archive needs a directory\n");
        exit(1);
    }
}

// Every string column in the content table may hold an image URL (the copy is HTML).
$referenced = [];
$res = $db->query('SELECT * FROM `majors_programs_content`');
while ($row = $res->fetch_assoc()) {
    foreach ($row as $v) {
        if (!is_string($v) || $v === '') {
            continue;
        }
        if (preg_match_all('#(?:https?://[^/\s"\']+)?/academics/majors/_images/([^\s"\'<>?]+)#i', $v, $m)) {
            foreach ($m[1] as $f) {
                $referenced[rawurldecode($f)] = true;
            }
        }
    }
}
// Program rows too (note field etc.).
$res = $db->query('SELECT `note` FROM `majors_academic_programs` WHERE `note` LIKE "%_images/%"');
while ($row = $res->fetch_assoc()) {
    if (preg_match_all('#/academics/majors/_images/([^\s"\'<>?]+)#i', (string) $row['note'], $m)) {
        foreach ($m[1] as $f) {
            $referenced[rawurldecode($f)] = true;
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

$manifest = dirname(__DIR__) . '/docs/images-manifest.txt';
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
