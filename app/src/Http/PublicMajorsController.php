<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Majors\Listings;
use Majors\Majors\ProgramRenderer;
use Majors\Support\Json;
use Majors\Support\Request;

/**
 * /academics/majors/index.php — Degree Programs listing and program pages.
 *
 *   index.php                      A–Z list of every program
 *   index.php?order=college        grouped by college
 *   index.php?filter=online        undergrad | graduate | online | minors | certificates | badges (majors.php, graduate.php,
 *                                  online.php, certificates.php and the *_by_college.php pages set these, as the CMS pages did)
 *   index.php?college=...&department=...&search=...
 *   index.php?program=<basename>   one program's marketing page (index.php?id=N redirects here)
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

        // One program: ?program=<basename> is the public address; ?id=N (the old
        // links) redirects to it so nothing that was bookmarked breaks.
        $basename = $r->strOrNull('program');
        $id       = $r->id('id');
        if (($basename !== null || $id !== null) && !$isJson) {
            $program = $basename !== null ? $programs->findByBasename($basename) : $programs->find((int) $id);
            if ($program === null && $basename !== null && ($renamed = $programs->findByAlias($basename)) !== null) {
                $program = $renamed;                          // an earlier page name: forward to the current one
                $basename = null;
            }
            if ($program !== null && ($program['status'] ?? 'active') === 'retired' && ($target = $programs->forwardTarget($program)) !== null) {
                $this->redirect($layout->programUrl($target), 301);   // a retired program combined into another page
            }
            if ($program === null || ($program['status'] ?? 'active') === 'retired') {
                $this->notFound('That program could not be found.');
            }
            // ?id=N, and any spelling of the name other than the stored one, redirect to the canonical address.
            if ((string) ($program['basename'] ?? '') !== '' && $basename !== (string) $program['basename']) {
                $this->redirect($layout->programUrl($program), 301);
            }
            $id    = (int) $program['id'];
            $parts = $renderer->parts($program, $this->app->maps()->forProgram($id));
            $this->page($parts['content'], [
                'top'         => $parts['top'],
                'bottom'      => $parts['bottom'],
                'title'       => ProgramRenderer::title($program),
                'page_header' => 'Details: ' . ProgramRenderer::title($program),
                'nav_items'   => $renderer->sectionNav($program),
                'header_print' => true,
                'description' => (string) ($program['meta_description'] ?? $program['content']['meta_description'] ?? ''),
                'head'        => [
                    '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                    (string) ($program['basename'] ?? '') !== '' ? '<link rel="canonical" href="' . $layout->e($layout->programUrl($program)) . '">' : '',
                ],
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

        $rows = $programs->listing(['filter' => $filter, 'order' => $order, 'search' => $search, 'college' => $college, 'department' => $department]);
        if ($filter === 'badges' && $rows === [] && !$isJson) {
            $this->redirect(Listings::BADGES_URL);   // badges have their own site, as on the CMS listing pages
        }

        $headline = ProgramRenderer::headline($filter, $college, $department, $search);
        $query    = http_build_query(array_filter(['order' => $order === 'alpha' ? null : $order, 'filter' => $filter === 'all' ? null : $filter,
            'college' => $college, 'department' => $department, 'search' => $search !== '' ? $search : null]));
        // A plain list view links to its own page (graduate.php, online_by_college.php…), like the CMS listing pages.
        $plain = $search === '' && $college === null && $department === null && $filter !== 'minors'
            && ($order === 'alpha' || (Listings::LISTS[$filter]['college'] ?? null) !== null);
        $resultsUrl = $query === '' ? null : ($plain ? Listings::url($layout->url(''), $filter, $order) : $layout->url('index.php') . '?' . $query);
        $certs = $filter === 'certificates' && $order === 'alpha';
        $vars  = [
            'groups'        => $certs ? [] : ProgramRenderer::group($rows, $order),
            'cert_sections' => $certs ? ProgramRenderer::groupCertificates($rows) : null,
            'intros'        => $certs ? $this->certificateIntros() : [],
            'headline'      => $headline,
            'order'         => $order,
            'results_url'   => $resultsUrl,
            'all_url'       => Listings::url($layout->url(''), 'all'),
        ];
        // Each design decides what sits in the full-width top band and what in the column:
        // the redesign puts the headline, buttons and letter index up top (majors/results_header),
        // the current design keeps them in the listing itself and renders nothing here.
        $header  = $layout->render('majors/results_header', $vars);
        $listing = $layout->render('majors/listing', $vars);

        if ($isJson) {
            Json::send(['success' => true, 'results' => $listing, 'header' => $header, 'title' => $headline, 'count' => count($rows)]);
        }

        $filtersBar = $layout->render('majors/filters', [
            'filters'    => self::filterLabels(),
            'filter'     => $filter,
            'order'      => $order,
            'search'     => $search,
            'college'    => $college,
            'colleges'   => $colleges,
            'self_url'   => $layout->url('index.php'),
            'search_url' => $layout->url('search.php'),
        ]);
        $content = $layout->render('majors/index', ['results' => $listing]);
        $this->page($content, [
            'top'         => $filtersBar . $header,
            'title'       => 'Degree Programs',
            'page_header' => 'Degree Programs',
            'nav_items'   => $renderer->sectionNav(),
            'description' => 'All Wichita State University degree programs.',
            'head'        => ['<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">'],
            'foot'        => ['<script src="' . $layout->e($layout->asset('majors.js')) . '" defer></script>'],
        ]);
    }

    /** @return array<string,string> filter => label for the listing's type select (badges live on their own site) */
    private static function filterLabels(): array
    {
        return ['all' => 'All Programs', 'undergrad' => 'Undergrad Majors & Minors', 'graduate' => 'Graduate Degrees', 'online' => 'Online',
            'minors' => 'Minors', 'certificates' => 'Certificates'];
    }

    /**
     * The two intros on the Certificates page ("What's a certificate?"), kept as shared blocks so
     * they can be edited on the Shared blocks page. @return array<string,array{headline:string,body:string}>
     */
    private function certificateIntros(): array
    {
        $out = [];
        $res = $this->app->db()->query("SELECT `slug`, `headline`, `body` FROM `majors_content_blocks` WHERE `slug` IN ('listing-intro-certificates-graduate', 'listing-intro-certificates-undergraduate')");
        while ($r = $res->fetch_assoc()) {
            $out[substr((string) $r['slug'], strlen('listing-intro-certificates-'))] = ['headline' => (string) $r['headline'], 'body' => (string) $r['body']];
        }
        return $out;
    }
}
