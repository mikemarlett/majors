<?php

/** share/urls.php, the address and visibility rules handed to code outside the app, pinned to the app's own. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\DegreeMaps\MapRepository;
use Majors\Majors\Listings;

$urls   = require dirname(__DIR__) . '/share/urls.php';
$layout = test_layout('old');

echo "[programs]\n";
$row = ['id' => 41, 'basename' => 'aerospace_engineering_bs_41', 'status' => 'active'];
check($urls['program']($row) === 'https://www.wichita.edu' . $layout->programUrl($row), 'program address matches Layout::programUrl');
check($urls['program'](['id' => 9, 'basename' => '']) === 'https://www.wichita.edu' . $layout->programUrl(['id' => 9]), 'a program without a page name falls back to ?id=');
check($urls['program'](['basename' => 'a b/c'], 'https://www-dev.wichita.edu') === 'https://www-dev.wichita.edu/academics/majors/index.php?program=a%20b%2Fc', 'name is URL-encoded, site is a parameter');
check($urls['program_visible']($row) && !$urls['program_visible'](['status' => 'retired'] + $row) && !$urls['program_visible'](['status' => 'retired', 'forward_to' => 3]), 'only active programs are visible');

echo "[degree maps]\n";
check($urls['degree_map'](922) === 'https://www.wichita.edu/academics/majors/degree_maps/maps.php?degree_map_id=922', 'map address');
check($urls['degree_map_latest'](922) === 'https://www.wichita.edu/academics/majors/degree_maps/maps.php?latest=922', 'permanent address');
check($urls['degree_map_visible'](['approved' => 1]) && !$urls['degree_map_visible'](['approved' => 0]) && !$urls['degree_map_visible'](['approved' => null]) && !$urls['degree_map_visible']([]), 'approved = 1 only');
$ts = mktime(12, 0, 0, 10, 8, 2026);
check($urls['degree_map_default_year']([2024, 2025, 2026, 2027], $ts) === MapRepository::currentAcademicYear($ts) && MapRepository::currentAcademicYear($ts) === 2027, 'default year is the current catalog year when it has approved maps');
check($urls['degree_map_default_year']([2024, 2025, 2026, 2028], $ts) === 2026, 'else the newest earlier year');
check($urls['degree_map_default_year']([2028], $ts) === 2028, 'else whatever exists');

echo "[listings]\n";
foreach (['all', 'undergrad', 'graduate', 'online', 'certificates', 'badges'] as $list) {
    foreach (['alpha', 'college'] as $order) {
        $expect = Listings::url('https://www.wichita.edu/academics/majors', $list, $order);
        check($urls['listing']($list, $order) === $expect, "listing {$list}/{$order} matches Listings::url ({$expect})");
    }
}

finish();
