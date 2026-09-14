<?php

/**
 * Compatibility shim. The CMS-hosted /_resources/php/degree_maps_search_process.php
 * (and possibly other site scripts) `require` this file by its old path and
 * call a handful of the legacy functions. Nothing in this app uses them; the
 * public page now talks to degree_maps/search.php. Keep until those site
 * scripts are retired, then delete.
 */

declare(strict_types=1);

if (!isset($app)) {
    require dirname(__DIR__) . '/_bootstrap.php';
}
/** @var \Majors\Kernel $app */
$__maps   = $app->maps();
$__layout = $app->layout();

$academic_year = (int) ($_REQUEST['selected_year'] ?? $_REQUEST['academic_year'] ?? 0) ?: $__maps->defaultYear();
$order         = (($_REQUEST['order'] ?? '') === 'college') ? 'college' : 'alpha';
$college       = isset($_REQUEST['selected_college']) ? trim((string) $_REQUEST['selected_college']) : null;
$degree_map_id = (int) ($_REQUEST['degree_map_id'] ?? $_REQUEST['map_id'] ?? 0) ?: null;
$linkBase      = $__layout->url('degree_maps/maps.php') . '?degree_map_id=';

if (!function_exists('get_colleges_array')) {
    function get_colleges_array(?int $year = null): array
    {
        global $__maps, $academic_year;
        return $__maps->colleges($year ?: (int) $academic_year);
    }
    function get_year(): int
    {
        global $__maps;
        return $__maps->latestYear();
    }
    function getCurrentAcademicYear(): int
    {
        return \Majors\DegreeMaps\MapRepository::currentAcademicYear();
    }
    function get_map_by_id(int $id): ?array
    {
        global $__maps;
        return $__maps->find($id);
    }
    function get_map_title(int $id): string
    {
        global $__maps;
        return $__maps->title($id) ?? 'Degree Map';
    }
    function maps_by_alpha(array $maps = []): string
    {
        global $__maps, $__layout, $academic_year, $college, $linkBase;
        $rows = $maps ?: $__maps->list((int) $academic_year, 'alpha', $college);
        return $__layout->render('degree_maps/listing', ['groups' => \Majors\DegreeMaps\Listing::byAlpha($rows), 'link_base' => $linkBase]);
    }
    function maps_by_college(array $maps = []): string
    {
        global $__maps, $__layout, $academic_year, $college, $linkBase;
        $rows = $maps ?: $__maps->list((int) $academic_year, 'college', $college);
        return $__layout->render('degree_maps/listing', ['groups' => \Majors\DegreeMaps\Listing::byCollege($rows), 'link_base' => $linkBase]);
    }
    function display_search_list(array $maps, string $order): string
    {
        return $order === 'college' ? maps_by_college($maps) : maps_by_alpha($maps);
    }
    function search_degree_maps(string $text = '', string $field = ''): ?array
    {
        global $__maps, $academic_year, $college, $order;
        $rows = $__maps->search($text, (int) $academic_year, $field === 'college' || $field === 'both' ? $college : null, $order);
        return $rows === [] ? null : $rows;
    }
    function display_degree_map(int|array $map): string
    {
        global $__maps, $__layout;
        if (!is_array($map) || !isset($map['courses'])) {
            $map = $__maps->find(is_array($map) ? (int) $map['id'] : $map);
        }
        return $map ? (new \Majors\DegreeMaps\MapRenderer($__layout))->render($map) : '';
    }
}
