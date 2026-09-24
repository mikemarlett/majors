<?php

/**
 * Seed graduate Program Details from the catalog (catalog.wichita.edu).
 *
 *   php bin/majors-catalog-seed.php --dry-run
 *   php bin/majors-catalog-seed.php                   # fills catalog_url, degree_title, credit_hours where empty
 *   php bin/majors-catalog-seed.php --overwrite       # replaces existing values too
 *   php bin/majors-catalog-seed.php --cache=/dir      # reuse fetched pages (<md5 of url>.html)
 *   php bin/majors-catalog-seed.php --all             # not only graduate programs
 *
 * The catalog URL comes from the program's own sections (the Curriculum link
 * marketing put on the page). Pages are fetched politely (~5/s) with a UA.
 */

declare(strict_types=1);

use Majors\Majors\CatalogSeeder;

$opts  = getopt('', ['dry-run', 'overwrite', 'cache::', 'all', 'only::']);
$dry   = isset($opts['dry-run']);
$over  = isset($opts['overwrite']);
$cache = isset($opts['cache']) ? rtrim((string) $opts['cache'], '/') : sys_get_temp_dir() . '/majors-catalog';
@mkdir($cache, 0775, true);

$app = require dirname(__DIR__) . '/bootstrap.php';
$db  = $app->db();

$sql = 'SELECT p.`id`, p.`basename`, p.`catalog_url`, p.`degree_title`, p.`credit_hours`, p.`graduate`
          FROM `majors_academic_programs` p WHERE p.`status` = "active"' . (isset($opts['all']) ? '' : ' AND p.`graduate` = 1') . (isset($opts['only']) ? ' AND p.`basename` = "' . $db->real_escape_string((string) $opts['only']) . '"' : '');
$programs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

$linkFor = static function (int $id) use ($db): ?string {
    $res = $db->query('SELECT s.`links`, s.`headline` FROM `majors_program_sections` s WHERE s.`program_id` = ' . $id . ' AND s.`links` LIKE "%catalog.wichita.edu%" ORDER BY (s.`headline` = "Curriculum") DESC');
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        foreach (json_decode((string) $r['links'], true) ?: [] as $l) {
            if (str_contains((string) ($l['href'] ?? ''), 'catalog.wichita.edu')) {
                return preg_replace('~#.*$~', '', preg_replace('~^http://~', 'https://', (string) $l['href']) ?? '') ?: null;
            }
        }
    }
    return null;
};
$fetch = static function (string $url) use ($cache): ?string {
    static $last = 0.0;
    $file = $cache . '/' . md5($url) . '.html';
    if (is_file($file) && filesize($file) > 0) {
        return (string) file_get_contents($file);
    }
    $wait = 0.2 - (microtime(true) - $last);
    if ($wait > 0) {
        usleep((int) ($wait * 1e6));
    }
    $last = microtime(true);
    $ctx  = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'header' => "User-Agent: WSUMajorsImporter/0.1 (majors catalog seed)\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body !== false && $body !== '') {
        file_put_contents($file, $body);
        return $body;
    }
    return null;
};

$n = ['seen' => 0, 'no_link' => 0, 'fetch_failed' => 0, 'updated' => 0, 'unchanged' => 0, 'no_hours' => 0];
$stmt = $db->prepare('UPDATE `majors_academic_programs` SET `catalog_url` = ?, `degree_title` = ?, `credit_hours` = ? WHERE `id` = ?');
foreach ($programs as $p) {
    $n['seen']++;
    $url = $p['catalog_url'] ?: $linkFor((int) $p['id']);
    if ($url === null) {
        $n['no_link']++;
        echo "  no catalog link: {$p['basename']}\n";
        continue;
    }
    $html = $fetch($url);
    if ($html === null) {
        $n['fetch_failed']++;
        echo "  fetch failed: {$p['basename']} $url\n";
        continue;
    }
    $x = CatalogSeeder::extract($html);
    if ($x['credit_hours'] === '') {
        $n['no_hours']++;
    }
    $new = [
        'catalog_url'  => $url,
        'degree_title' => ($over || empty($p['degree_title'])) ? $x['degree_title'] : $p['degree_title'],
        'credit_hours' => ($over || empty($p['credit_hours'])) ? $x['credit_hours'] : $p['credit_hours'],
    ];
    if ($new['catalog_url'] === $p['catalog_url'] && $new['degree_title'] === $p['degree_title'] && $new['credit_hours'] === $p['credit_hours']) {
        $n['unchanged']++;
        continue;
    }
    $n['updated']++;
    if ($dry) {
        printf("  %-60s %-28s %s\n", $p['basename'], $new['degree_title'], $new['credit_hours'] !== '' ? $new['credit_hours'] . ' hrs' : '(no total)');
    } else {
        $stmt->bind_param('sssi', $new['catalog_url'], $new['degree_title'], $new['credit_hours'], $p['id']);
        $stmt->execute();
    }
}
foreach ($n as $k => $v) {
    printf("%-14s %d\n", $k, $v);
}
echo $dry ? "dry run complete\n" : "seed complete\n";
