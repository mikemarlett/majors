<?php
// Renderer parity: legacy display_degree_map() vs MapRenderer on real maps, compared as normalized text.
declare(strict_types=1);
$S = getenv('MAJORS_LEGACY_DIR') ?: '/tmp/majors-legacy'; // holds legacy/fakeroot/... from: git show 7e0fb7b:data/main/academics/majors/degree_maps/maps_functions.php
$_SERVER['DOCUMENT_ROOT'] = $S . '/legacy/fakeroot';
$_REQUEST = [];
require $_SERVER['DOCUMENT_ROOT'] . '/academics/majors/degree_maps/maps_functions.php'; // legacy, brings $mysqli
$app = require '/srv/work/majors/app/bootstrap.php';
$layout = $app->layout();
$maps = $app->maps();
$renderer = new \Majors\DegreeMaps\MapRenderer($layout);

$norm = static function (string $html): string {
    $html = preg_replace('#<nav class="dm-versions.*?</nav>#s', '', $html);           // new: version bar
    $html = preg_replace('#<img[^>]*yellow-line[^>]*>#', '', $html);                    // legacy: yellow line image
    $html = str_replace('&nbsp;', ' ', $html);
    $html = preg_replace('/>\s*</', '> <', $html);                                      // adjacent cells are separate words
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = str_replace('Institutionally Designated/Diversity', 'Institutionally Designated', $text);
    return trim($text);
};
$ids = array_column($mysqli->query('SELECT id FROM degree_maps ORDER BY RAND() LIMIT 80')->fetch_all(MYSQLI_ASSOC), 'id');
$ids = array_merge([152, 99], $ids); // the two odd families too
$same = 0; $diff = [];
foreach ($ids as $id) {
    $id = (int) $id;
    $old = $norm(display_degree_map($id));
    $new = $norm($renderer->render($maps->find($id)));
    if ($old === $new) { $same++; continue; }
    // first differing 80 chars
    $i = strspn($old ^ $new, "\0");
    $diff[$id] = [substr($old, max(0, $i - 40), 120), substr($new, max(0, $i - 40), 120)];
}
echo "identical text for {$same} of " . count($ids) . " maps\n";
foreach (array_slice($diff, 0, 6, true) as $id => [$o, $n]) {
    echo "\n#{$id}\n  old: {$o}\n  new: {$n}\n";
}
// Structural checks on one map: same number of table rows and cells.
$id = (int) $ids[2];
$o = display_degree_map($id); $n = $renderer->render($maps->find($id));
printf("\nmap #%d: tables old=%d new=%d, rows old=%d new=%d, cells old=%d new=%d\n", $id,
    substr_count($o, '<table'), substr_count($n, '<table'), substr_count($o, '<tr'), substr_count($n, '<tr'), substr_count($o, '<td'), substr_count($n, '<td'));
