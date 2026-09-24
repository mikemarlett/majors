<?php

/**
 * Refresh the majors tables from the CMS program pages.
 *
 *   php bin/majors-import.php --dry-run                  # parse everything, write nothing, print the summary
 *   php bin/majors-import.php                            # import from the CMS (site www)
 *   php bin/majors-import.php --site=www-dev
 *   php bin/majors-import.php --cache=/path/to/pcfs      # read <basename>.xml files from a folder instead of the API
 *   php bin/majors-import.php --only=aerospace_engineering_bs_100
 *   php bin/majors-import.php --no-retire                # don't mark programs whose page is gone
 *   php bin/majors-import.php --tags=/path/tags.json     # where resolved link tags are cached (default: system temp)
 *
 * Needs the Modern Campus helpers at /data/www/config (login + API); the
 * database comes from the app's own config (MAJORS_SITE=www-test for the CLI).
 */

declare(strict_types=1);

use Majors\Majors\CmsClient;
use Majors\Majors\CmsPageParser;
use Majors\Majors\Importer;

$opts   = getopt('', ['dry-run', 'site::', 'cache::', 'only::', 'no-retire', 'tags::', 'limit::']);
$dry    = isset($opts['dry-run']);
$site   = (string) ($opts['site'] ?? 'www');
$cache  = isset($opts['cache']) ? rtrim((string) $opts['cache'], '/') : null;
$only   = isset($opts['only']) ? (string) $opts['only'] : null;
$limit  = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$tags   = (string) ($opts['tags'] ?? (sys_get_temp_dir() . '/majors-cms-tags-' . $site . '.json'));

$app = require dirname(__DIR__) . '/bootstrap.php';
$cms = new CmsClient($site, tagCache: $tags);

echo $dry ? "DRY RUN — nothing will be written\n" : '';
// The page list comes from the API; with --cache, a _list.tsv (basename<TAB>date) beside the
// cached sources stands in when the API is unreachable, so an offline re-import is possible.
try {
    $list = $cms->listProgramPages();
    echo count($list) . " pages listed in {$site}:/academics/majors\n";
} catch (\Throwable $e) {
    if ($cache === null || !is_file($cache . '/_list.tsv')) {
        throw $e;
    }
    $list = [];
    foreach (file($cache . '/_list.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$b, $d] = array_pad(explode("\t", $line), 2, '');
        $list[] = ['basename' => $b, 'path' => '/academics/majors/' . $b . '.pcf', 'date' => $d, 'size' => 0];
    }
    echo count($list) . " pages from {$cache}/_list.tsv (API unreachable: " . substr($e->getMessage(), 0, 80) . ")\n";
}

$pages = [];
$skipped = [];
foreach ($list as $i => $entry) {
    if ($only !== null && $entry['basename'] !== $only) {
        continue;
    }
    if ($limit > 0 && count($pages) >= $limit) {
        break;
    }
    $file = $cache !== null ? $cache . '/' . $entry['basename'] . '.xml' : null;
    $src  = $file !== null && is_file($file) ? (string) file_get_contents($file) : $cms->source($entry['path']);
    if (!str_contains($src, '<title>Details:')) {
        $skipped[] = $entry['basename'];
        continue;
    }
    $src  = $cms->resolveTags($src);
    $page = CmsPageParser::parse($src, $entry['basename']);
    $page['cms_path']      = $entry['path'];
    $page['cms_file_date'] = $entry['date'] !== '' ? date('Y-m-d H:i:s', strtotime($entry['date']) ?: time()) : null;
    $pages[] = $page;
    if (count($pages) % 50 === 0) {
        echo "  parsed " . count($pages) . "…\n";
        $cms->saveTagCache();
    }
}
$cms->saveTagCache();
printf("%d program pages parsed, %d non-program pages skipped (%s), %d link tags known\n", count($pages), count($skipped), implode(', ', $skipped), $cms->tagCount());

$importer = new Importer($app->db(), $dry);
$importer->run($pages, !isset($opts['no-retire']) && $only === null && $limit === 0);
foreach ($importer->counts() as $k => $v) {
    printf("  %-20s %d\n", $k, $v);
}
foreach ($importer->notes() as $n) {
    echo "  · $n\n";
}
echo $dry ? "dry run complete\n" : "import complete\n";
