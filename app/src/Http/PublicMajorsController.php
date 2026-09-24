<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Majors\ProgramRenderer;
use Majors\Support\Json;
use Majors\Support\Request;

/**
 * /academics/majors/index.php — Degree Programs listing and program pages.
 *
 *   index.php                      A–Z list of every program
 *   index.php?order=college        grouped by college
 *   index.php?filter=online        undergrad | graduate | online | minors | certificates | badges
 *   index.php?college=...&department=...&search=...
 *   index.php?id=N                 one program's marketing page
 *
 * search.php (same controller, json=1) returns {results, title} for majors.js.
 */
final class PublicMajorsController extends Controller
{
    public function handle(Request $r): void
    {
        $layout   = $this->app->layout();
        $programs = $this->app->programs();
        $renderer = new ProgramRenderer($layout);
        $isJson   = basename($r->path(), '.php') === 'search';

        $id = $r->id('id');
        if ($id !== null && !$isJson) {
            $program = $programs->find($id);
            if ($program === null) {
                $this->notFound('That program could not be found.');
            }
            $parts = $renderer->parts($program, $this->app->maps()->forProgram($id));
            $this->page($parts['content'], [
                'top'         => $parts['top'],
                'bottom'      => $parts['bottom'],
                'title'       => ProgramRenderer::title($program),
                'page_header' => 'Details: ' . ProgramRenderer::title($program),
                'nav_items'   => $renderer->sectionNav($program),
                'header_print' => true,
                'description' => (string) ($program['content']['meta_description'] ?? ''),
                'head'        => ['<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">'],
            ]);
            return;
        }

        // Listing filters. Legacy flags (?online=1, ?graduate=1 ...) still work.
        $filter = $r->enum('filter', \Majors\Majors\ProgramRepository::FILTERS, 'all');
        if ($filter === 'all') {
            foreach (['undergrad', 'graduate', 'online', 'minors', 'certificates', 'badges'] as $legacy) {
                if ($r->int($legacy) === 1) {
                    $filter = $legacy;
                }
            }
        }
        $order      = $r->enum('order', ['alpha', 'college'], 'alpha');
        $search     = $r->str('search');
        $college    = $r->strOrNull('college');
        $department = $r->strOrNull('department');
        $colleges   = $programs->colleges();
        if ($college !== null && ($college === 'all' || !in_array($college, $colleges, true))) {
            $college = null;
        }
        if ($department !== null && !in_array($department, $programs->departments(), true)) {
            $department = null;
        }

        $unfiltered = $search === '' && $filter === 'all' && $college === null && $department === null;
        $rows = $unfiltered && $order === 'alpha'
            ? $programs->all()
            : $programs->search($search, ['filter' => $filter, 'college' => $college, 'department' => $department, 'order' => $order]);

        $headline = ProgramRenderer::headline($filter, $college, $department, $search);
        $query    = http_build_query(array_filter(['order' => $order === 'alpha' ? null : $order, 'filter' => $filter === 'all' ? null : $filter,
            'college' => $college, 'department' => $department, 'search' => $search !== '' ? $search : null]));
        $listing  = $layout->render('majors/listing', [
            'groups'      => ProgramRenderer::group($rows, $order),
            'headline'    => $headline,
            'order'       => $order,
            'link_base'   => $layout->url('index.php') . '?id=',
            'results_url' => $query !== '' ? $layout->url('index.php') . '?' . $query : null,
            'all_url'     => $layout->url('index.php'),
        ]);

        if ($isJson) {
            Json::send(['success' => true, 'results' => $listing, 'title' => $headline, 'count' => count($rows)]);
        }

        $content = $layout->render('majors/index', [
            'results'    => $listing,
            'filter'     => $filter,
            'order'      => $order,
            'search'     => $search,
            'college'    => $college,
            'colleges'   => $colleges,
            'self_url'   => $layout->url('index.php'),
            'search_url' => $layout->url('search.php'),
        ]);
        $this->page($content, [
            'title'       => 'Degree Programs',
            'page_header' => 'Degree Programs',
            'nav_items'   => $renderer->sectionNav(),
            'description' => 'All Wichita State University degree programs.',
            'head'        => ['<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">'],
            'foot'        => ['<script src="' . $layout->e($layout->asset('majors.js')) . '" defer></script>'],
        ]);
    }
}
