<?php

/**
 * Public addresses and visibility rules of the database-driven pages, for code
 * outside this app that links to them: the site search's connector_dbpages.php,
 * the site's ai-meta.php include. Pure functions, no dependencies, safe to
 * require from anywhere on a WSU box:
 *
 *   $urls = require '/data/www/config/majors/share/urls.php';
 *   $urls['program']($row)                 // $row: a majors_academic_programs row (basename, id)
 *   $urls['program_visible']($row)         // false for retired programs (their old address forwards)
 *   $urls['degree_map']($id)               // one catalog year's map
 *   $urls['degree_map_latest']($id)        // permanent: the newest approved year of that degree
 *   $urls['degree_map_visible']($row)      // false until an advisor admin approves it
 *   $urls['listing']('graduate')           // all|undergrad|graduate|online|certificates|badges, order alpha|college
 *
 * These are the rules of Layout::programUrl(), Listings::url(), the public
 * viewers and MapRepository; when any of those changes, change this with it
 * (tests/share_urls.php pins them).
 */

declare(strict_types=1);

$site = 'https://www.wichita.edu';
$base = '/academics/majors';

return [
    'site' => $site,
    'base' => $site . $base,

    'program' => static function (array $row, string $site = 'https://www.wichita.edu'): string {
        $b = (string) ($row['basename'] ?? '');
        return $site . '/academics/majors/index.php' . ($b !== '' ? '?program=' . rawurlencode($b) : '?id=' . (int) ($row['id'] ?? 0));
    },
    'program_visible' => static fn (array $row): bool => (($row['status'] ?? 'active') === 'active'),

    'degree_map'        => static fn (int $id, string $site = 'https://www.wichita.edu'): string => $site . '/academics/majors/degree_maps/maps.php?degree_map_id=' . $id,
    'degree_map_latest' => static fn (int $id, string $site = 'https://www.wichita.edu'): string => $site . '/academics/majors/degree_maps/maps.php?latest=' . $id,
    'degree_map_visible' => static fn (array $row): bool => ((int) ($row['approved'] ?? 0) === 1),
    // The year the public page shows by default: the current catalog year (August rolls over) when it
    // has approved maps, else the newest earlier year that does. $yearsWithApprovedMaps: ints, any order.
    'degree_map_default_year' => static function (array $yearsWithApprovedMaps, ?int $now = null): int {
        $now ??= time();
        $current = (int) date('Y', $now) + ((int) date('n', $now) >= 8 ? 1 : 0);
        $years = array_map('intval', $yearsWithApprovedMaps);
        rsort($years);
        if (in_array($current, $years, true)) {
            return $current;
        }
        foreach ($years as $y) {
            if ($y <= $current) {
                return $y;
            }
        }
        return $years[0] ?? $current;
    },

    'listing' => static function (string $list, string $order = 'alpha', string $site = 'https://www.wichita.edu'): string {
        $pages = [
            'all'          => ['index.php', 'index_by_college.php'],
            'undergrad'    => ['majors.php', 'majors_by_college.php'],
            'graduate'     => ['graduate.php', 'graduate_by_college.php'],
            'online'       => ['online.php', 'online_by_college.php'],
            'certificates' => ['certificates.php', null],
        ];
        if ($list === 'badges') {
            return 'https://badges.wichita.edu/badge/';
        }
        [$alpha, $college] = $pages[$list] ?? $pages['all'];
        return $site . '/academics/majors/' . ($order === 'college' && $college !== null ? $college : $alpha);
    },
];
