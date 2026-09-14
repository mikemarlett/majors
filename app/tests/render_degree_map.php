<?php

/** Renders the fixture map under both designs and checks the parts that matter for print and parity. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\DegreeMaps\MapRenderer;

$map = json_decode((string) file_get_contents(__DIR__ . '/fixtures/degree_map.json'), true, 512, JSON_THROW_ON_ERROR);

foreach (['old', 'new'] as $design) {
    echo "[$design]\n";
    $layout = test_layout($design);
    $html   = (new MapRenderer($layout))->render($map, [
        'versions'   => [['id' => 950, 'academic_year' => 2027], ['id' => 922, 'academic_year' => 2026], ['id' => 800, 'academic_year' => 2025]],
        'latest_id'  => 950,
        'link_base'  => '/academics/majors/degree_maps/maps.php?degree_map_id=',
        'latest_url' => '/academics/majors/degree_maps/maps.php?latest=922',
    ]);

    check(str_contains($html, 'dm-table--summer'), 'first year has a summer column');
    check(substr_count($html, '<table') === 2, 'two year tables');
    check(str_contains($html, 'dm-sge-030">030</span>'), 'SGE badge rendered');
    check(str_contains($html, 'href="#footnote_11"') && str_contains($html, 'MATH 242 Calculus I&nbsp;<sup><a'), 'inline <sup>1</sup> replaced by footnote link');
    check(str_contains($html, 'href="#footnote_12"'), 'appended footnote link for course without inline sup');
    check(str_contains($html, '<span class="dm-extra">Fall only</span>'), 'extra note rendered escaped');
    check(str_contains($html, 'Total hours for 1st year: <strong>34</strong>'), 'year 1 total summed from semester hours (16+15+3)');
    check(str_contains($html, 'Total hours for 2nd year: <strong>31</strong>'), 'year 2 uses manual year override');
    check(str_contains($html, '<strong>Note: </strong>Courses in <em>italics</em>'), 'order-0 footnote shown as note');
    check(str_contains($html, '<li id="footnote_11">'), 'numbered footnotes listed');
    check(str_contains($html, 'Hours needed to complete the degree: 128'), 'hours to graduate');
    check(str_contains($html, 'You are viewing the 2025 - 2026 catalog-year version'), 'older-version notice');
    check(str_contains($html, '?latest=922'), 'permanent latest link shown');
    check(!str_contains($html, 'STUB SITE HEADER'), 'map body contains no chrome');
    check(str_contains($html, 'dm-blank'), 'blank cells fill ragged semesters');

    $page = $layout->page($html, ['title' => 'T']);
    check(str_contains($page, 'STUB SITE HEADER'), 'full page includes the stub chrome');
    check(str_contains($page, 'class="site-chrome site-chrome--header"'), 'chrome wrapped for print hiding');
}

$css = (string) file_get_contents(dirname(__DIR__, 2) . '/docroot/academics/majors/assets/degree-map.css');
check(str_contains($css, 'size: letter portrait'), 'print CSS pins letter portrait');
check(str_contains($css, '.site-chrome, .noprint'), 'print CSS hides chrome and noprint');

// Empty theme dir → fragments are '' and nothing leaks.
$empty = new \Majors\View\Theme('/nonexistent/dir');
check($empty->fragment('header') === '', 'missing theme dir degrades to empty fragments');

finish();
