<?php

/**
 * Bring the hand-kept CMS listing pages (All Programs, Undergrad Majors & Minors, Graduate,
 * Online, Certificates and the by-college views) into majors_listing_entries.
 *
 *   php bin/majors-listing-import.php --dry-run            # parse, compare, print what would change
 *   php bin/majors-listing-import.php                      # write the entries (one transaction)
 *   php bin/majors-listing-import.php --sql=/path/file.sql # write the same change as SQL keyed by page name
 *   php bin/majors-listing-import.php --cache=/path/dir    # read/keep <page>.xml and asset-<id>.html there
 *   php bin/majors-listing-import.php --tags=/path/tags.json
 *   php bin/majors-listing-import.php --site=www
 *   php bin/majors-listing-import.php --dry-run --show=nursing_family_nurse_practitioner_dnp_313,counseling_med_41
 *
 * Refuses to run once majors.cms_import is false in the config (pass --force to override).
 *
 * Re-runnable while the CMS listings are the live ones. Pages whose listings were changed in
 * the editor are left alone, and so are programs created in the editor that no CMS list shows.
 * Needs the Modern Campus helpers at /data/www/config; the database comes from the app config
 * (MAJORS_SITE=www-test for the CLI).
 */

declare(strict_types=1);

use Majors\Majors\CmsClient;
use Majors\Majors\ListingImporter;

$opts  = getopt('', ['dry-run', 'site::', 'cache::', 'tags::', 'sql::', 'show::', 'force']);
foreach (['site', 'cache', 'tags', 'sql', 'show'] as $o) {           // getopt only takes these as --name=value; "--sql /path" arrives empty
    if (isset($opts[$o]) && ($opts[$o] === false || $opts[$o] === '')) {
        fwrite(STDERR, "--{$o} needs a value, written as --{$o}=…\n");
        exit(1);
    }
}
$dry   = isset($opts['dry-run']);
$site  = (string) ($opts['site'] ?? 'www');
$cache = isset($opts['cache']) ? rtrim((string) $opts['cache'], '/') : null;
$tags  = (string) ($opts['tags'] ?? (sys_get_temp_dir() . '/majors-cms-tags-' . $site . '.json'));
$sqlTo = isset($opts['sql']) ? (string) $opts['sql'] : null;

$app = require dirname(__DIR__) . '/bootstrap.php';
if (!(bool) $app->config->get('majors.cms_import', true) && !isset($opts['force'])) {
    fwrite(STDERR, "The CMS import is switched off in the config (majors.cms_import = false): the listings are kept in the\n"
        . "editor now, and the CMS listing pages are out of date. Pass --force to run it anyway.\n");
    exit(1);
}
$db  = $app->db();
$cms = new CmsClient($site, tagCache: $tags);
$imp = new ListingImporter();

$read = static function (string $name, callable $fetch) use ($cache): string {
    $file = $cache !== null ? "{$cache}/{$name}" : null;
    if ($file !== null && is_file($file)) {
        return (string) file_get_contents($file);
    }
    $content = $fetch();
    if ($file !== null) {
        @mkdir($cache, 0775, true);
        file_put_contents($file, $content);
    }
    return $content;
};
$assetOf = static function (string $page) use ($read, $cms): string {
    $src = $read($page . '.xml', static fn () => $cms->source('/academics/majors/' . $page . '.pcf'));
    if (!preg_match('~label="maincontent".*?\{\{a:(\d+)\}\}~s', $src, $m)) {
        throw new RuntimeException("The listing page {$page} does not include a shared list asset.");
    }
    return $read('asset-' . $m[1] . '.html', static fn () => $cms->asset((int) $m[1]));
};
$unresolved = [];
$basename = static function (array $line) use ($cms, &$unresolved): ?string {
    $url = $cms->resolveTags($line['href']);
    if (preg_match('~/academics/majors/([A-Za-z0-9_\-]+)\.php$~', $url, $m)) {
        return $m[1];
    }
    $unresolved[$line['href']] = $line['name'];
    return null;
};

echo $dry ? "DRY RUN — nothing will be written\n" : '';
$lists = [];
$certs = [];
foreach (ListingImporter::PAGES as $list => [$azPage, $collegePage]) {
    if ($list === 'certificates') {
        $intros = ListingImporter::parseCertificateIntros($assetOf($azPage));
        foreach (ListingImporter::parseCertificates($assetOf($azPage)) as $l) {
            if (($l['basename'] = $basename($l)) !== null) {
                $certs[] = $l;
            }
        }
        printf("%-14s %4d lines (%s)\n", $list, count($certs), $azPage);
        continue;
    }
    foreach (['az' => $azPage, 'college' => $collegePage] as $view => $page) {
        $lines = [];
        foreach (ListingImporter::parseList($assetOf($page)) as $l) {
            if (($l['basename'] = $basename($l)) !== null) {
                $lines[] = $l;
            }
        }
        $lists[$list][$view] = $lines;
        printf("%-14s %4d lines (%s)\n", $list, count($lines), $page);
    }
}
$cms->saveTagCache();

$rows = $db->query('SELECT `basename`, `academic_program`, `status`, (`cms_path` IS NOT NULL AND `cms_path` <> "") AS imported FROM `majors_academic_programs` WHERE `basename` <> ""')->fetch_all(MYSQLI_ASSOC);
$names = array_column($rows, 'academic_program', 'basename');
$plan  = ListingImporter::plan($lists, $certs, $names);

$entries = array_sum(array_map('count', $plan));
$renamed = 0;
$azOnly  = 0;
$multi   = 0;
foreach ($plan as $b => $es) {
    $multi += count($es) > 1 ? 1 : 0;
    foreach ($es as $e) {
        $renamed += $e['name'] !== null ? 1 : 0;
        $azOnly  += $e['shown_in'] === 'az' ? 1 : 0;
    }
}
printf("\n%d pages on the CMS lists, %d entries (%d under a name other than the page's, %d A–Z only, %d pages with more than one entry)\n", count($plan), $entries, $renamed, $azOnly, $multi);
if ($unresolved !== []) {
    echo count($unresolved) . " links could not be resolved to a program page:\n";
    foreach ($unresolved as $href => $name) {
        echo "  {$href}  {$name}\n";
    }
}

foreach (array_filter(explode(',', (string) ($opts['show'] ?? ''))) as $b) {
    echo "\n{$b}  ({$names[$b]})\n";
    foreach ($plan[$b] ?? [] as $i => $e) {
        printf("  %d. %s — %s  [%s] %s%s\n", $i + 1, $e['name'] ?? '(page name)', $e['detail'], implode(',', $e['lists']), $e['shown_in'],
            $e['cert_section'] !== null ? "  certificates: {$e['cert_section']} / {$e['cert_topics']}" : '');
    }
    if (($plan[$b] ?? []) === []) {
        echo "  (not listed)\n";
    }
}

if ($sqlTo !== null) {
    file_put_contents($sqlTo, $imp->sql($db, $plan, 'generated ' . date('Y-m-d H:i') . ' from the CMS (' . $site . ')', $intros ?? []));
    echo "SQL written to {$sqlTo}\n";
    $summary = $imp->apply($db, $plan, true);
} else {
    $summary = $imp->apply($db, $plan, $dry);
}
$added = $imp->applyIntros($db, $intros ?? [], $dry || $sqlTo !== null);
if ($added !== []) {
    echo ($dry || $sqlTo !== null ? 'would add' : 'added') . ' the Certificates intro block(s): ' . implode(', ', $added) . "\n";
}
$counts = array_count_values($summary);
ksort($counts);
echo "\n" . ($dry || $sqlTo !== null ? 'would be: ' : 'done: ') . implode(', ', array_map(static fn ($k, $n) => "{$n} {$k}", array_keys($counts), $counts)) . "\n";
$byBase = array_column($rows, null, 'basename');
foreach ($summary as $b => $what) {
    if ($what === 'unlisted' && ($byBase[$b]['status'] ?? '') === 'active') {
        echo "  not on any CMS list (active): {$b}  {$names[$b]}\n";
    } elseif ($what !== 'listed' && $what !== 'unlisted') {
        echo "  {$what}: {$b}\n";
    }
}
foreach (array_unique($imp->notes) as $n) {
    echo "  note: {$n}\n";
}
