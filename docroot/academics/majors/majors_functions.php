<?php

/**
 * Compatibility shim for the CMS-hosted /_resources/php/degree_search.php,
 * which requires this file by its old path and calls search_majors(),
 * get_search_headline(), majors_by_alpha() / majors_by_college() and reads
 * $filters. The public page now uses search.php instead. Delete once the
 * site script is retired.
 */

declare(strict_types=1);

if (!isset($app)) {
    require __DIR__ . '/_bootstrap.php';
}
/** @var \Majors\Kernel $app */
$__programs = $app->programs();
$__layout   = $app->layout();

$search  = isset($_REQUEST['search']) ? trim(strip_tags((string) $_REQUEST['search'])) : '';
$order   = (($_REQUEST['order'] ?? '') === 'college') ? 'college' : 'alpha';
$filter  = in_array($_REQUEST['filter'] ?? '', \Majors\Majors\ProgramRepository::FILTERS, true) ? $_REQUEST['filter'] : 'all';
$college = isset($_REQUEST['college']) && in_array($_REQUEST['college'], $__programs->colleges(), true) ? $_REQUEST['college'] : null;
$department = isset($_REQUEST['department']) && in_array($_REQUEST['department'], $__programs->departments(), true) ? $_REQUEST['department'] : null;
$filters = ['order' => $order, 'college' => $college, 'department' => $department, 'filter' => $filter, 'action' => 'home'];
foreach (['undergrad', 'graduate', 'online', 'minors', 'certificates', 'badges'] as $__f) {
    $filters[$__f] = $filter === $__f ? 1 : null;
}

if (!function_exists('search_majors')) {
    function search_majors(string $text = ''): array
    {
        global $__programs, $filters, $search;
        return $__programs->search($text !== '' ? $text : $search, $filters);
    }
    function get_search_headline(): string
    {
        global $filters, $search;
        return \Majors\Majors\ProgramRenderer::headline($filters['filter'], $filters['college'], $filters['department'], $search);
    }
    function majors_by_alpha(array $majors = []): string
    {
        global $__layout;
        return $__layout->render('majors/listing', ['groups' => \Majors\Majors\ProgramRenderer::group($majors, 'alpha'), 'headline' => get_search_headline(),
            'order' => 'alpha', 'link_base' => $__layout->url('index.php') . '?id=', 'results_url' => null, 'all_url' => $__layout->url('index.php')]);
    }
    function majors_by_college(array $majors = []): string
    {
        global $__layout;
        return $__layout->render('majors/listing', ['groups' => \Majors\Majors\ProgramRenderer::group($majors, 'college'), 'headline' => get_search_headline(),
            'order' => 'college', 'link_base' => $__layout->url('index.php') . '?id=', 'results_url' => null, 'all_url' => $__layout->url('index.php')]);
    }
}
